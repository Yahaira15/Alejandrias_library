import json
from unittest.mock import patch

from django.test import RequestFactory, SimpleTestCase

from .chat_logic import construir_respuesta_foros, es_peticion_de_foros
from .context_manager import construir_contexto
from .moderation import extraer_json_moderacion, moderacion_respaldo, normalizar_resultado_moderacion
from .orchestrator import preparar_ejecucion
from .views import chat_view, moderation_view


VIEW_GENERAR_TEXTO = f"{chat_view.__module__}.generar_texto"
ORCHESTRATOR_FOROS = f"{preparar_ejecucion.__module__}.obtener_foros_existentes"


class OrquestacionIATests(SimpleTestCase):
    def test_contexto_chat_normaliza_historial_e_intencion(self):
        contexto = construir_contexto(
            {
                "tipo": "chat",
                "data": {
                    "mensaje": "   Que fue la antigua Roma?   ",
                    "historial": [
                        {"rol": "asistente", "texto": "Hola"},
                        {"rol": "usuario", "texto": "   Ensename historia  "},
                        {"rol": "otro", "texto": "ignorar"},
                    ],
                },
            }
        )

        self.assertEqual(contexto["mensaje"], "Que fue la antigua Roma?")
        self.assertEqual(contexto["intencion"], "explicacion")
        self.assertEqual(
            contexto["historial"],
            [
                {"rol": "asistente", "texto": "Hola"},
                {"rol": "usuario", "texto": "Ensename historia"},
            ],
        )

    def test_contexto_detecta_explicacion_en_quien_fue(self):
        contexto = construir_contexto(
            {
                "tipo": "chat",
                "data": {
                    "mensaje": "Quien fue Carlos Augusto",
                    "historial": [],
                },
            }
        )

        self.assertEqual(contexto["intencion"], "explicacion")

    def test_contexto_detecta_tema_corto_como_explicacion(self):
        contexto = construir_contexto(
            {
                "tipo": "chat",
                "data": {
                    "mensaje": "alejandro magno",
                    "historial": [],
                },
            }
        )

        self.assertEqual(contexto["intencion"], "explicacion")

    def test_orquestador_chat_construye_prompt_rico(self):
        ejecucion = preparar_ejecucion(
            {
                "tipo": "chat",
                "data": {
                    "mensaje": "Explica la antigua Roma",
                    "historial": [{"rol": "usuario", "texto": "Quiero aprender historia"}],
                },
            }
        )

        self.assertEqual(ejecucion["tarea"], "chat_texto")
        self.assertIn("ORQUESTACION:", ejecucion["prompt"])
        self.assertIn("Intencion detectada: explicacion", ejecucion["prompt"])

    def test_respuesta_foros_recomienda_existentes(self):
        respuesta = construir_respuesta_foros(
            {"mensaje": "Recomiendame foros sobre roma antigua", "historial": []},
            [
                {
                    "titulo": "Historia de Roma",
                    "descripcion": "Debates sobre republica e imperio romano",
                    "categoria": "Historia",
                },
                {
                    "titulo": "Egipto antiguo",
                    "descripcion": "Conversaciones sobre faraones y templos",
                    "categoria": "Historia",
                },
            ],
        )

        self.assertIn("Historia de Roma", respuesta)
        self.assertNotIn("invent", respuesta.lower())

    def test_respuesta_foros_creacion_no_depende_de_backend(self):
        respuesta = construir_respuesta_foros(
            {
                "mensaje": "ayudame a definir el titulo del foro",
                "historial": [
                    {"rol": "usuario", "texto": "Me ayudarias a crear un foro sobre la antigua grecia con sus creencias"}
                ],
            },
            [],
        )

        self.assertIn("titulo", respuesta.lower())
        self.assertIn("grecia", respuesta.lower())
        self.assertNotIn("no pude cargar", respuesta.lower())

    def test_es_peticion_de_foros_no_se_pega_por_historial(self):
        contexto = {
            "mensaje": "quien fue cleopatra",
            "historial": [
                {"rol": "usuario", "texto": "que foros me recomiendas?"},
                {"rol": "asistente", "texto": "Estos foros existentes te pueden servir mejor..."},
            ],
        }
        self.assertFalse(es_peticion_de_foros(contexto))

    @patch(VIEW_GENERAR_TEXTO, return_value="Te recomiendo Historia de Roma.")
    @patch(ORCHESTRATOR_FOROS)
    def test_chat_view_envia_foros_existentes_a_gemini(self, obtener_foros_existentes, generar_texto):
        obtener_foros_existentes.return_value = [
            {
                "id": 1,
                "titulo": "Historia de Roma",
                "descripcion": "Debates sobre republica e imperio romano",
                "categoria": "Historia",
                "creador": "admin",
                "privado": False,
            }
        ]

        request = RequestFactory().post(
            "/api/ia/chat/",
            data='{"tipo":"chat","data":{"mensaje":"Recomiendame foros sobre roma"}}',
            content_type="application/json",
        )

        response = chat_view(request)

        self.assertEqual(response.status_code, 200)
        self.assertIn(b'"origen": "modelo"', response.content)
        self.assertIn(b"Historia de Roma", response.content)
        generar_texto.assert_called_once()

    def test_moderacion_permite_contexto_historico_educativo(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "publicacion",
                "contenido": {
                    "titulo": "Guerras napoleonicas",
                    "texto": "Analisis historico sobre causas y consecuencias de la guerra en Europa.",
                },
                "contexto": {"categoria": "Historia"},
            }
        )

        self.assertEqual(resultado["estado"], "permitido")
        self.assertTrue(resultado["valor_educativo"])
        self.assertLess(resultado["riesgo"], 0.3)

    def test_moderacion_permite_conversacion_inocente(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "publicacion",
                "contenido": {"titulo": "fotos", "texto": "hola foto"},
            }
        )

        self.assertEqual(resultado["estado"], "permitido")
        self.assertIn(resultado["categoria"], {"conversacional", "ocio"})
        self.assertFalse(resultado["requiere_revision_humana"])

    def test_moderacion_permite_framework_como_tecnologia(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "publicacion",
                "contenido": {"texto": "Que es un framework?"},
            }
        )

        self.assertEqual(resultado["estado"], "permitido")
        self.assertIn(resultado["categoria"], {"tecnologia", "educativo"})

    def test_moderacion_permite_ocio_inocente(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "publicacion",
                "contenido": {"texto": "Pandas rojos con tinte rojo"},
            }
        )

        self.assertEqual(resultado["estado"], "permitido")
        self.assertIn(resultado["categoria"], {"ocio", "conversacional"})

    def test_moderacion_permite_tema_educativo_religioso(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "publicacion",
                "contenido": {
                    "titulo": "El taoismo",
                    "texto": "El taoismo es una corriente filosofica y religiosa con origen en la antigua China.",
                },
            }
        )

        self.assertEqual(resultado["estado"], "permitido")
        self.assertEqual(resultado["categoria"], "educativo")

    def test_moderacion_permite_opinion_informal_de_videojuegos(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "publicacion",
                "contenido": {"texto": "Ese juego es malisimo jajaja"},
            }
        )

        self.assertEqual(resultado["estado"], "permitido")
        self.assertEqual(resultado["categoria"], "ocio")

    def test_moderacion_permite_virus_informaticos_educativo(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "publicacion",
                "contenido": {"texto": "Como funcionan virus informaticos en ciberseguridad defensiva"},
            }
        )

        self.assertEqual(resultado["estado"], "permitido")
        self.assertEqual(resultado["categoria"], "tecnologia")

    def test_moderacion_no_bloquea_palabra_amenaza_en_contexto(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "publicacion",
                "contenido": {"texto": "Analisis historico de una amenaza politica en Roma"},
            }
        )

        self.assertEqual(resultado["estado"], "permitido")
        self.assertEqual(resultado["categoria"], "educativo")

    def test_normalizacion_suaviza_revision_otro_benigna(self):
        resultado = normalizar_resultado_moderacion(
            {
                "estado": "revision",
                "riesgo": 0.42,
                "categoria": "otro",
                "razon": "Contenido ambiguo.",
                "accion_recomendada": "enviar_revision",
                "valor_educativo": False,
                "requiere_revision_humana": True,
            },
            {
                "tipo_contenido": "publicacion",
                "contenido": {"texto": "Antecedentes de la caida de Roma"},
            },
        )

        self.assertEqual(resultado["estado"], "permitido")
        self.assertEqual(resultado["categoria"], "educativo")
        self.assertFalse(resultado["requiere_revision_humana"])

    def test_normalizacion_permite_ddos_si_es_educativo(self):
        resultado = normalizar_resultado_moderacion(
            {
                "estado": "revision",
                "riesgo": 0.5,
                "categoria": "otro",
                "razon": "Tema sensible.",
                "accion_recomendada": "enviar_revision",
                "valor_educativo": True,
                "requiere_revision_humana": True,
            },
            {
                "tipo_contenido": "publicacion",
                "contenido": {"texto": "Como funcionan ataques DDOS en ciberseguridad defensiva"},
            },
        )

        self.assertEqual(resultado["estado"], "permitido")
        self.assertEqual(resultado["categoria"], "tecnologia")
        self.assertTrue(resultado["valor_educativo"])

    def test_moderacion_envia_toxicidad_leve_a_revision(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "comentario",
                "contenido": {"texto": "Tu argumento es estupido, no sabes nada."},
            }
        )

        self.assertEqual(resultado["estado"], "revision")
        self.assertTrue(resultado["requiere_revision_humana"])

    def test_moderacion_bloquea_amenaza_directa(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "comentario",
                "contenido": {"texto": "Te voy a matar si vuelves a comentar."},
            }
        )

        self.assertEqual(resultado["estado"], "bloqueado")
        self.assertGreaterEqual(resultado["riesgo"], 0.9)

    def test_moderacion_bloquea_asesinato_directo(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "publicacion",
                "contenido": {"titulo": "Te voy a asesinar", "texto": "te matare"},
            }
        )

        self.assertEqual(resultado["estado"], "bloqueado")
        self.assertEqual(resultado["categoria"], "violencia")

    def test_moderacion_bloquea_amenaza_grupal(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "publicacion",
                "contenido": {"titulo": "matar", "texto": "matar a todos"},
            }
        )

        self.assertEqual(resultado["estado"], "bloqueado")
        self.assertEqual(resultado["categoria"], "violencia")

    def test_moderacion_bloquea_bombas_hacking_y_doxxing(self):
        casos = [
            ("Como fabricar bombas", "ilegal"),
            ("Hackea tarjetas bancarias", "ilegal"),
            ("Publica su direccion", "acoso"),
        ]

        for texto, categoria in casos:
            with self.subTest(texto=texto):
                resultado = moderacion_respaldo({"contenido": {"texto": texto}})
                self.assertEqual(resultado["estado"], "bloqueado")
                self.assertEqual(resultado["categoria"], categoria)

    def test_moderacion_bloquea_trafico_animal(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "publicacion",
                "contenido": {"titulo": "Trafico animal", "texto": "vendo un condor de los andes en 8 millones"},
            }
        )

        self.assertEqual(resultado["estado"], "bloqueado")
        self.assertEqual(resultado["categoria"], "ilegal")

    def test_moderacion_bloquea_trafico_personas(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "publicacion",
                "contenido": {"titulo": "Vendo un nino y una nina", "texto": "no hacen caso"},
            }
        )

        self.assertEqual(resultado["estado"], "bloqueado")
        self.assertEqual(resultado["categoria"], "ilegal")

    def test_moderacion_envia_ventas_generales_a_revision(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "publicacion",
                "contenido": {
                    "titulo": "Cartas mas valiosas del panini",
                    "texto": "empiezo con Messi, cuanto vale?",
                },
            }
        )

        self.assertEqual(resultado["estado"], "revision")
        self.assertEqual(resultado["categoria"], "otro")

    def test_moderacion_envia_spam_sospechoso_a_revision(self):
        casos = [
            "Compra seguidores baratos aqui",
            "Gana dinero rapido",
            "Mira este link extrano",
        ]

        for texto in casos:
            with self.subTest(texto=texto):
                resultado = moderacion_respaldo({"contenido": {"texto": texto}})
                self.assertEqual(resultado["estado"], "revision")

    def test_normalizacion_no_permite_respuesta_gemini_insegura(self):
        resultado = normalizar_resultado_moderacion(
            {
                "estado": "permitido",
                "riesgo": 0.1,
                "categoria": "conversacional",
                "razon": "Conversacion normal.",
                "accion_recomendada": "publicar",
                "valor_educativo": False,
                "requiere_revision_humana": False,
            },
            {
                "tipo_contenido": "publicacion",
                "contenido": {"titulo": "Te voy a asesinar", "texto": "te matare"},
            },
        )

        self.assertEqual(resultado["estado"], "bloqueado")
        self.assertEqual(resultado["categoria"], "violencia")

    def test_normalizacion_no_permite_venta_ilegal(self):
        resultado = normalizar_resultado_moderacion(
            {
                "estado": "permitido",
                "riesgo": 0.1,
                "categoria": "ocio",
                "razon": "Publicacion de ocio.",
                "accion_recomendada": "publicar",
                "valor_educativo": False,
                "requiere_revision_humana": False,
            },
            {
                "tipo_contenido": "publicacion",
                "contenido": {"titulo": "Trafico animal", "texto": "vendo un condor de los andes"},
            },
        )

        self.assertEqual(resultado["estado"], "bloqueado")
        self.assertEqual(resultado["categoria"], "ilegal")

    def test_normalizacion_envia_venta_general_a_revision(self):
        resultado = normalizar_resultado_moderacion(
            {
                "estado": "permitido",
                "riesgo": 0.1,
                "categoria": "ocio",
                "razon": "Coleccionismo normal.",
                "accion_recomendada": "publicar",
                "valor_educativo": False,
                "requiere_revision_humana": False,
            },
            {
                "tipo_contenido": "publicacion",
                "contenido": {"titulo": "Cartas mas valiosas del panini", "texto": "empiezo con Messi, cuanto vale?"},
            },
        )

        self.assertEqual(resultado["estado"], "revision")
        self.assertEqual(resultado["categoria"], "otro")
        self.assertTrue(resultado["requiere_revision_humana"])

    def test_moderacion_bloquea_acoso_grave(self):
        resultado = moderacion_respaldo(
            {
                "tipo_contenido": "comentario",
                "contenido": {"texto": "Esto es estupido, mejor muere."},
            }
        )

        self.assertEqual(resultado["estado"], "bloqueado")
        self.assertEqual(resultado["categoria"], "acoso")

    def test_extraer_json_moderacion_acepta_markdown_json(self):
        data, json_limpio = extraer_json_moderacion(
            """```json
{
  "estado": "permitido",
  "riesgo": 0.1,
  "categoria": "educativo",
  "razon": "Contenido sobre programacion.",
  "accion_recomendada": "publicar",
  "valor_educativo": true,
  "requiere_revision_humana": false
}
```"""
        )

        self.assertEqual(data["estado"], "permitido")
        self.assertEqual(data["categoria"], "educativo")
        self.assertIn('"estado"', json_limpio)

    def test_extraer_json_moderacion_acepta_texto_alrededor(self):
        data, _ = extraer_json_moderacion(
            'Resultado:\n{"estado":"bloqueado","riesgo":0.94,"categoria":"violencia","razon":"Amenaza directa.","accion_recomendada":"bloquear","valor_educativo":false,"requiere_revision_humana":true}\nFin.'
        )

        self.assertEqual(data["estado"], "bloqueado")
        self.assertEqual(data["categoria"], "violencia")

    @patch(VIEW_GENERAR_TEXTO, return_value='```json\n{"estado":"permitido","riesgo":0.08,"categoria":"educativo","razon":"Contenido de programacion permitido.","accion_recomendada":"publicar","valor_educativo":true,"requiere_revision_humana":false}\n```')
    def test_moderation_view_no_usa_respaldo_con_markdown_json(self, generar_texto):
        request = RequestFactory().post(
            "/api/ia/moderacion/",
            data='{"tipo_contenido":"publicacion","contenido":{"texto":"PHP es divertido"},"contexto":{"categoria":"Programacion"}}',
            content_type="application/json",
        )

        response = moderation_view(request)
        payload = json.loads(response.content.decode("utf-8"))

        self.assertEqual(response.status_code, 200)
        self.assertEqual(payload["origen"], "modelo")
        self.assertFalse(payload["debug"]["fallback"])
        self.assertEqual(payload["data"]["estado"], "permitido")
        self.assertEqual(payload["data"]["categoria"], "educativo")
        generar_texto.assert_called_once()

    @patch(VIEW_GENERAR_TEXTO, side_effect=RuntimeError("sin red"))
    def test_moderation_view_responde_respaldo(self, generar_texto):
        request = RequestFactory().post(
            "/api/ia/moderacion/",
            data='{"tipo_contenido":"publicacion","contenido":{"titulo":"Anatomia humana","texto":"Estudio educativo del cuerpo humano"},"contexto":{"categoria":"Ciencia"}}',
            content_type="application/json",
        )

        response = moderation_view(request)

        self.assertEqual(response.status_code, 200)
        self.assertIn(b'"ok": true', response.content)
        self.assertIn(b'"estado": "permitido"', response.content)
        generar_texto.assert_called_once()

    @patch(ORCHESTRATOR_FOROS)
    def test_orquestador_recomendador_carga_foros_si_no_vienen_en_payload(self, obtener_foros_existentes):
        obtener_foros_existentes.return_value = [
            {
                "id": 1,
                "titulo": "Historia de Roma",
                "descripcion": "Debates sobre republica e imperio romano",
                "categoria": "Historia",
                "creador": "admin",
                "privado": False,
            }
        ]

        ejecucion = preparar_ejecucion(
            {
                "tipo": "recomendador",
                "data": {
                    "intereses": ["roma"],
                },
            }
        )

        self.assertEqual(ejecucion["tarea"], "recomendar_foros")
        self.assertIn("Historia de Roma", ejecucion["prompt"])

    @patch(VIEW_GENERAR_TEXTO, side_effect=RuntimeError("sin red"))
    @patch(ORCHESTRATOR_FOROS)
    def test_chat_view_creacion_foro_usa_respaldo_util_si_modelo_falla(self, obtener_foros_existentes, generar_texto):
        obtener_foros_existentes.return_value = []

        request = RequestFactory().post(
            "/api/ia/chat/",
            data=(
                '{"tipo":"chat","data":{"mensaje":"ayudame a definir el titulo del foro",'
                '"historial":[{"rol":"usuario","texto":"Me ayudarias a crear un foro sobre la antigua grecia con sus creencias"}]}}'
            ),
            content_type="application/json",
        )

        response = chat_view(request)

        self.assertEqual(response.status_code, 200)
        self.assertIn(b'"origen": "respaldo"', response.content)
        self.assertIn(b"grecia", response.content.lower())
        self.assertNotIn(b"no pude cargar", response.content.lower())
        generar_texto.assert_called_once()
