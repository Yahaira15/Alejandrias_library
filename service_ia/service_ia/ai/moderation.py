import json
import re
import unicodedata


ESTADOS_VALIDOS = {"permitido", "revision", "bloqueado"}
CATEGORIAS_VALIDAS = {
    "educativo",
    "conversacional",
    "ocio",
    "tecnologia",
    "toxicidad",
    "spam",
    "sexual",
    "violencia",
    "odio",
    "acoso",
    "ilegal",
    "autolesion",
    "otro",
}

NIVELES_ALERTA_SEGURIDAD = {"ninguno", "riesgo_medio", "riesgo_alto", "riesgo_critico"}

SAFETY_CATEGORY_MAP = {
    "HARM_CATEGORY_HATE_SPEECH": "odio",
    "HATE_SPEECH": "odio",
    "HARM_CATEGORY_HARASSMENT": "acoso",
    "HARASSMENT": "acoso",
    "HARM_CATEGORY_SEXUALLY_EXPLICIT": "sexual",
    "SEXUALLY_EXPLICIT": "sexual",
    "HARM_CATEGORY_DANGEROUS_CONTENT": "ilegal",
    "DANGEROUS_CONTENT": "ilegal",
}

SAFETY_SCORE = {
    "NEGLIGIBLE": 0.0,
    "LOW": 0.25,
    "MEDIUM": 0.6,
    "HIGH": 0.9,
}

MODERATION_RESPONSE_SCHEMA = {
    "type": "object",
    "properties": {
        "estado": {"type": "string", "enum": ["permitido", "revision", "bloqueado"]},
        "riesgo": {"type": "number"},
        "categoria": {
            "type": "string",
            "enum": [
                "educativo",
                "conversacional",
                "ocio",
                "tecnologia",
                "toxicidad",
                "spam",
                "sexual",
                "violencia",
                "odio",
                "acoso",
                "ilegal",
                "autolesion",
                "otro",
            ],
        },
        "razon": {"type": "string"},
        "accion_recomendada": {"type": "string", "enum": ["publicar", "ocultar", "enviar_revision", "bloquear"]},
        "valor_educativo": {"type": "boolean"},
        "requiere_revision_humana": {"type": "boolean"},
    },
    "required": [
        "estado",
        "riesgo",
        "categoria",
        "razon",
        "accion_recomendada",
        "valor_educativo",
        "requiere_revision_humana",
    ],
}

PROMPT_MODERACION = """
Eres el motor de moderacion contextual de Alejandrias Library, una comunidad educativa.

Tu tarea es analizar contenido generado por usuarios y devolver SOLO JSON valido.

Principios:
- La IA no elimina contenido: analiza, clasifica, explica y recomienda.
- Aplica un sesgo permisivo solo ante contenido normal: si hay amenaza, odio, abuso, venta ilegal o dano real claro, no lo permitas.
- El contexto educativo importa. Historia, guerras historicas, anatomia, filosofia, ciencia,
  tecnologia, programacion, lenguajes como PHP, Python o JavaScript, o debates sociales respetuosos no deben bloquearse por defecto.
- Diferencia contenido educativo legitimo de contenido danino real.
- No seas extremista ni excesivamente restrictivo. No moderes por palabras aisladas: analiza intencion, contexto y significado completo.
- Palabras fuertes dentro de historia, noticias, literatura, ejemplos tecnicos, ciberseguridad defensiva o debates normales no son violencia real por si solas.

Estados:
- permitido: contenido educativo, conversacional, ocio, historico, tecnico, programacion, debates normales, humor leve o expresiones inocentes con riesgo bajo.
- revision: insultos directos, agresividad moderada, toxicidad leve, provocacion, ambiguedad o sospecha concreta.
- revision: tambien spam, publicidad sospechosa, "gana dinero rapido", compra de seguidores, links raros, ventas, compras,
  subastas, ofertas, precios o intercambios comerciales. Aunque puedan ser inocentes,
  en esta comunidad educativa deben inspeccionarse antes de publicarse.
- bloqueado: amenazas reales, violencia explicita contra personas reales, sexual explicito, odio extremo,
  terrorismo, autolesion, explotacion, trata/trafico de personas, menores, venta ilegal, fauna silvestre/protegida, drogas,
  armas, doxxing, extorsion, estafas graves, hacking malicioso o instrucciones claramente daninas.

Ejemplos de permitido:
- "Antecedentes de la caida de Roma" -> educativo, permitido.
- "Que es un framework?" -> tecnologia o educativo, permitido.
- "Pandas rojos con tinte rojo" -> ocio o conversacional, permitido.
- "Historia de guerras romanas" -> educativo, permitido; no es violencia real.
- "Como funcionan ataques DDOS" -> tecnologia, permitido si es explicacion educativa o defensiva.
- "Como funcionan virus informaticos" -> tecnologia, permitido si es explicacion educativa o defensiva.
- "Ese juego es malisimo jajaja" -> ocio, permitido; opinion informal sin ataque personal.

Ejemplos de revision:
- "Eres estupido" -> toxicidad, revision.
- "Compra seguidores baratos aqui" -> spam, revision.
- "Vendo cuentas premium" -> otro, revision.
- "Gana dinero rapido" -> spam, revision.

Ejemplos de bloqueado:
- "Te voy a matar" -> violencia, bloqueado.
- "Te voy a asesinar" -> violencia, bloqueado.
- "Te matare" -> violencia, bloqueado.
- "Matar a todos" -> violencia, bloqueado.
- "Voy a asesinarlo" -> violencia, bloqueado.
- "Como fabricar bombas" -> ilegal, bloqueado.
- "Hackea tarjetas bancarias" -> ilegal, bloqueado.
- "Publica su direccion" -> acoso, bloqueado.
- "Vendo un condor de los Andes" -> ilegal, bloqueado.
- "Trafico animal" -> ilegal, bloqueado.
- "Vendo un niño y una niña" -> ilegal, bloqueado.
- "Odio a [grupo protegido] y hay que exterminarlos" -> odio, bloqueado.

Devuelve exactamente este JSON:
{
  "estado": "permitido|revision|bloqueado",
  "riesgo": 0.0,
  "categoria": "educativo|conversacional|ocio|tecnologia|toxicidad|spam|sexual|violencia|odio|acoso|ilegal|autolesion|otro",
  "razon": "explicacion breve y contextual",
  "accion_recomendada": "publicar|ocultar|enviar_revision|bloquear",
  "valor_educativo": true,
  "requiere_revision_humana": false
}
""".strip()


def _limpiar_texto(valor):
    return " ".join(str(valor or "").strip().split())


def _normalizar(valor):
    texto = unicodedata.normalize("NFKD", _limpiar_texto(valor))
    texto = "".join(caracter for caracter in texto if not unicodedata.combining(caracter))
    return texto.lower()


def construir_prompt_moderacion(payload):
    contenido = payload.get("contenido") or {}
    contexto = payload.get("contexto") or {}

    return "\n\n".join(
        [
            PROMPT_MODERACION,
            "CONTENIDO A MODERAR:",
            json.dumps(
                {
                    "tipo": payload.get("tipo_contenido", "contenido"),
                    "titulo": contenido.get("titulo", ""),
                    "texto": contenido.get("texto", ""),
                    "nombre": contenido.get("nombre", ""),
                },
                ensure_ascii=False,
            ),
            "CONTEXTO:",
            json.dumps(contexto, ensure_ascii=False),
        ]
    )


def extraer_json_moderacion(texto):
    texto_limpio = str(texto or "").strip()
    if not texto_limpio:
        raise ValueError("Gemini devolvio una respuesta vacia antes del parseo JSON.")

    candidatos = []

    for match in re.finditer(r"```(?:json)?\s*(.*?)```", texto_limpio, flags=re.IGNORECASE | re.DOTALL):
        bloque = match.group(1).strip()
        if bloque:
            candidatos.append(bloque)

    candidatos.append(texto_limpio)

    inicio = texto_limpio.find("{")
    fin = texto_limpio.rfind("}")
    if inicio != -1 and fin != -1 and fin > inicio:
        candidatos.append(texto_limpio[inicio : fin + 1])

    errores = []
    decoder = json.JSONDecoder()
    for candidato in candidatos:
        candidato = candidato.strip()
        if not candidato:
            continue

        try:
            data = json.loads(candidato)
        except json.JSONDecodeError as exc:
            try:
                data, _ = decoder.raw_decode(candidato)
            except json.JSONDecodeError as raw_exc:
                errores.append(f"{raw_exc.msg} en posicion {raw_exc.pos}")
                continue

        if isinstance(data, dict):
            return data, candidato

        errores.append(f"JSON parseado no es objeto: {type(data).__name__}")

    detalle = "; ".join(errores[-3:]) if errores else "sin candidatos JSON utiles"
    raise ValueError(f"No se pudo extraer JSON valido de la respuesta de Gemini: {detalle}")


def _contiene(texto, patrones):
    return any(re.search(patron, texto) for patron in patrones)


def _patrones_educativos():
    return [
        r"\bhistoria\b",
        r"\bantecedente(s)?\b",
        r"\broma\b|\bromana(s)?\b|\bromano(s)?\b",
        r"\bguerra(s)? historica(s)?\b",
        r"\bcaida de\b",
        r"\banatomia\b",
        r"\bciencia\b",
        r"\bmatematica(s)?\b",
        r"\bfilosofia\b",
        r"\bfilosofic",
        r"\breligion\b|\breligiosa(s)?\b|\breligioso(s)?\b",
        r"\btaoismo\b|\btao\b|\bbudismo\b|\bcristianismo\b|\bislam\b|\bhinduismo\b",
        r"\bchina\b|\bantigua china\b|\bcultura(s)?\b",
        r"\bliteratura\b",
        r"\baprend",
        r"\bestudi",
        r"\bexplica(r|cion)?\b",
        r"\bque es\b|\bcomo funciona(n)?\b",
    ]


def _patrones_tecnologia():
    return [
        r"\bprogramacion\b",
        r"\bframework(s)?\b",
        r"\bphp\b|\bpython\b|\bjavascript\b|\bjava\b|\blaravel\b|\bdjango\b",
        r"\bapi\b|\bbackend\b|\bfrontend\b|\bdatabase\b|\bpostgresql\b",
        r"\bciberseguridad\b|\bddos\b|\bvirus informatico(s)?\b|\bmalware\b|\bseguridad informatica\b",
    ]


def _patrones_ocio():
    return [
        r"\bfoto(s)?\b|\bimagen(es)?\b|\bpanda(s)?\b",
        r"\bjuego(s)?\b|\bpelicula(s)?\b|\bmusica\b",
        r"\bmalisimo\b.*\bjajaja\b|\bjajaja\b",
        r"\bhumor\b|\bbroma(s)?\b|\bmeme(s)?\b",
    ]


def _patrones_conversacionales():
    return [
        r"\bhola\b|\bbuenas\b|\bhey\b",
        r"\bgracias\b|\bpor favor\b",
        r"\bprobando\b|\bprueba\b|\btest\b",
        r"\bopinion\b|\bdebate\b|\bconversacion\b",
    ]


def _patrones_riesgo_real():
    return [
        r"\bte voy a matar\b|\bvoy a matarte\b|\bquiero matarte\b|\bte matare\b|\bte mato\b",
        r"\bte voy a asesinar\b|\bvoy a asesinarte\b|\bquiero asesinarte\b|\bte asesinare\b|\bte asesino\b|\bvoy a asesinarlo\b|\basesinarlo\b",
        r"\bvoy a matar a\b|\bvoy a asesinar a\b|\bplaneo matar\b|\bplaneo asesinar\b|\bvoy a disparar\b|\bvoy a apunalar\b",
        r"\bmatar a todos\b|\bmatare a todos\b|\bmataremos a todos\b|\bmatar personas\b|\bmatarlos a todos\b",
        r"\bmejor muere\b|\bmatate\b|\bquiero que mueras\b",
        r"\bporn(o|ografia)\b|\bsexo explicito\b|\bxxx\b",
        r"\bodio a\b|\bexterminar\b|\bdiscrimin",
        r"\bsuicid",
        r"\bcomprar droga(s)?\b|\bvender droga(s)?\b|\bcomprar arma(s)?\b|\bvender arma(s)?\b",
        r"\bfabricar bomba(s)?\b|\bcomo fabricar bomba(s)?\b|\bhacer explosivo(s)?\b",
        r"\bhackea(r)? tarjeta(s)? bancaria(s)?\b|\brobar tarjeta(s)?\b|\brobar banco(s)?\b|\bphishing\b.*\bclave(s)?\b",
        r"\bpublica(r)? su direccion\b|\bpublica(r)? su telefono\b|\bdoxx(ing)?\b|\bfiltra(r)? sus datos\b",
        r"\bextorsion\b|\bchantaje\b|\bestafa grave\b",
        r"\btrafico animal\b|\btraficar animal(es)?\b|\bventa ilegal de animal(es)?\b",
        r"\b(vendo|vender|compro|comprar|trafico|traficar)\b.*\b(condor(es)?|fauna silvestre|animal(es)? silvestre(s)?|especie(s)? protegida(s)?)\b",
        r"\btrata de personas\b|\btrafico de personas\b|\btraficar personas\b|\bventa de personas\b",
        r"\b(vendo|vender|compro|comprar|trafico|traficar)\b.*\b(nino(s)?|nina(s)?|menor(es)?|persona(s)?|humano(s)?|bebe(s)?)\b",
        r"(https?://\S+\s*){3,}",
        r"\bidiota\b|\bestupido\b|\bimbecil\b|\bcallate\b|\bno sabes nada\b",
    ]


def _detectar_alerta_seguridad(texto):
    if not texto:
        return {
            "requiere_alerta": False,
            "nivel": "ninguno",
            "tipo": "ninguno",
            "razon": "",
        }

    reglas_criticas = [
        (
            r"\b(me voy a matar|voy a suicidarme|me suicidare|quiero suicidarme hoy|hoy me mato)\b"
            r"|\b(tengo un plan|ya tengo plan|tengo la cuerda|tengo pastillas|tengo un arma)\b.*\b(matarme|suicid|hacerme dano)\b",
            "autolesion",
            "Intencion directa o planificacion de autolesion.",
        ),
        (
            r"\b(voy a matar a|voy a asesinar a|planeo matar|planeo asesinar|voy a disparar|voy a apunalar)\b"
            r"|\b(tengo un arma|tengo un cuchillo)\b.*\b(matar|asesinar|herir|atacar)\b",
            "violencia",
            "Amenaza grave o planificacion de dano contra otras personas.",
        ),
    ]

    reglas_altas = [
        (
            r"\b(me quiero matar|quiero matarme|quiero suicidarme|suicidarme|suicidio)\b"
            r"|\b(me voy a cortar|quiero cortarme|me quiero cortar|autolesion|hacerme dano|hacerme mucho dano)\b",
            "autolesion",
            "Senales explicitas de autolesion o suicidio.",
        ),
        (
            r"\b(te voy a matar|voy a matarte|quiero matarte|te matare|te voy a asesinar|voy a asesinarte|matar a todos)\b"
            r"|\b(quiero matar a alguien|quiero hacerle dano|quiero herir a alguien)\b",
            "violencia",
            "Amenaza o intencion explicita de violencia grave.",
        ),
    ]

    reglas_medias = [
        (
            r"\b(no quiero vivir|ya no quiero vivir|no puedo mas|no aguanto mas|quiero desaparecer)\b"
            r"|\b(no le importo a nadie|no tengo esperanza|sin esperanza|todo estaria mejor sin mi)\b",
            "salud_mental",
            "Senales de desesperanza o crisis emocional intensa.",
        ),
        (
            r"\b(me siento vacio|me siento vacia|estoy desesperado|estoy desesperada|tristeza extrema)\b",
            "salud_mental",
            "Expresion de tristeza extrema o malestar emocional.",
        ),
    ]

    for reglas, nivel in [
        (reglas_criticas, "riesgo_critico"),
        (reglas_altas, "riesgo_alto"),
        (reglas_medias, "riesgo_medio"),
    ]:
        for patron, tipo, razon in reglas:
            if re.search(patron, texto):
                return {
                    "requiere_alerta": True,
                    "nivel": nivel,
                    "tipo": tipo,
                    "razon": razon,
                }

    return {
        "requiere_alerta": False,
        "nivel": "ninguno",
        "tipo": "ninguno",
        "razon": "",
    }


def _normalizar_safety_valor(valor):
    if valor is None:
        return ""

    texto = str(valor)
    if "." in texto:
        texto = texto.rsplit(".", 1)[-1]

    return texto.strip().upper()


def _iter_safety_ratings(metadata):
    if not isinstance(metadata, dict):
        return []

    ratings = []
    for candidato in metadata.get("candidates") or []:
        if not isinstance(candidato, dict):
            continue
        valores = candidato.get("safety_ratings") or []
        if isinstance(valores, dict):
            valores = [valores]
        for rating in valores:
            if isinstance(rating, dict):
                ratings.append(rating)

    prompt_feedback = metadata.get("prompt_feedback")
    if isinstance(prompt_feedback, dict):
        valores = prompt_feedback.get("safety_ratings") or []
        if isinstance(valores, dict):
            valores = [valores]
        for rating in valores:
            if isinstance(rating, dict):
                ratings.append(rating)

    return ratings


def _resumen_safety(metadata):
    resumen = []
    for rating in _iter_safety_ratings(metadata):
        categoria_original = _normalizar_safety_valor(rating.get("category"))
        probabilidad = _normalizar_safety_valor(rating.get("probability"))
        severidad = _normalizar_safety_valor(rating.get("severity"))
        score_probabilidad = float(rating.get("probability_score") or SAFETY_SCORE.get(probabilidad, 0.0) or 0.0)
        score_severidad = float(rating.get("severity_score") or SAFETY_SCORE.get(severidad, 0.0) or 0.0)

        resumen.append(
            {
                "categoria_original": categoria_original,
                "categoria": SAFETY_CATEGORY_MAP.get(categoria_original, "otro"),
                "probabilidad": probabilidad or "UNKNOWN",
                "severidad": severidad or "UNKNOWN",
                "score_probabilidad": round(max(0.0, min(1.0, score_probabilidad)), 2),
                "score_severidad": round(max(0.0, min(1.0, score_severidad)), 2),
                "bloqueado_por_filtro": bool(rating.get("blocked", False)),
            }
        )

    return resumen


def _aplicar_safety_oficial(estado, riesgo, categoria, alerta_seguridad, safety_summary):
    if not safety_summary:
        return estado, riesgo, categoria, alerta_seguridad

    rating_maximo = max(
        safety_summary,
        key=lambda item: max(item["score_probabilidad"], item["score_severidad"], 1.0 if item["bloqueado_por_filtro"] else 0.0),
    )
    score = max(
        rating_maximo["score_probabilidad"],
        rating_maximo["score_severidad"],
        1.0 if rating_maximo["bloqueado_por_filtro"] else 0.0,
    )
    categoria_safety = rating_maximo["categoria"]

    if score >= 0.9 or rating_maximo["bloqueado_por_filtro"]:
        estado = "bloqueado"
        riesgo = max(riesgo, 0.92)
        categoria = categoria_safety if categoria_safety in CATEGORIAS_VALIDAS else categoria
        alerta_seguridad = {
            "requiere_alerta": True,
            "nivel": "riesgo_critico",
            "tipo": categoria,
            "razon": "Los filtros oficiales de seguridad de Gemini/Vertex marcaron riesgo alto.",
        }
    elif score >= 0.6 and estado == "permitido":
        estado = "revision"
        riesgo = max(riesgo, 0.62)
        categoria = categoria_safety if categoria_safety in CATEGORIAS_VALIDAS else categoria

    return estado, riesgo, categoria, alerta_seguridad


def _patrones_venta_general():
    return [
        r"\b(vendo|vender|venta|compro|comprar|compra|subasto|subasta|oferto|oferta|intercambio|negocio)\b",
        r"\b(precio|cuanto vale|valor de venta|en venta|pago|pagaria|cobro)\b",
        r"\b(pesos|dolares|euros|cop|usd|millones|millon|barato|caro)\b",
        r"\b(carta(s)?|panini|album|coleccionable(s)?)\b.*\b(valiosa(s)?|precio|vendo|compro|oferta)\b",
        r"\bcompra seguidores\b|\bseguidores baratos\b|\bgana dinero rapido\b|\bdinero facil\b",
        r"\blink raro\b|\blink extrano\b|\blink sospechoso\b",
    ]


def _riesgo_real_detectado(texto):
    reglas_bloqueo = [
        (
            r"\bte voy a matar\b|\bvoy a matarte\b|\bquiero matarte\b|\bte matare\b|\bte mato\b"
            r"|\bte voy a asesinar\b|\bvoy a asesinarte\b|\bquiero asesinarte\b|\bte asesinare\b|\bte asesino\b"
            r"|\bvoy a asesinarlo\b|\basesinarlo\b|\bvoy a matar a\b|\bvoy a asesinar a\b|\bplaneo matar\b|\bplaneo asesinar\b"
            r"|\bvoy a disparar\b|\bvoy a apunalar\b|\bmatar a todos\b|\bmatare a todos\b|\bmataremos a todos\b|\bmatar personas\b|\bmatarlos a todos\b",
            "violencia",
            "Amenaza directa de dano fisico contra otra persona.",
        ),
        (
            r"\bcomo matar\b|\bcomo asesinar\b|\bcomo puedo matar\b|\bcomo puedo asesinar\b"
            r"|\bmatar a mis profesor(es)?\b|\basesinar a mis profesor(es)?\b",
            "violencia",
            "Solicitud o intencion relacionada con asesinato o violencia grave.",
        ),
        (
            r"\bmejor muere\b|\bmatate\b|\bquiero que mueras\b",
            "acoso",
            "Acoso grave o incitacion a dano personal.",
        ),
        (
            r"\bporn(o|ografia)\b|\bsexo explicito\b|\bxxx\b",
            "sexual",
            "Contenido sexual explicito.",
        ),
        (
            r"\bodio a\b.*\b(exterminar|matar|eliminar)\b|\bexterminar\b.*\b(grupo|raza|religion|mujeres|hombres|personas)\b|\bdiscrimin",
            "odio",
            "Odio extremo o discriminacion.",
        ),
        (
            r"\bsuicid|\bautolesion\b|\bme quiero matar\b",
            "autolesion",
            "Posible contenido de autolesion.",
        ),
        (
            r"\bcomprar droga(s)?\b|\bvender droga(s)?\b|\bcomprar arma(s)?\b|\bvender arma(s)?\b",
            "ilegal",
            "Promocion de compra o venta ilegal.",
        ),
        (
            r"\bfabricar bomba(s)?\b|\bcomo fabricar bomba(s)?\b|\bhacer explosivo(s)?\b",
            "ilegal",
            "Instrucciones o solicitud para fabricar explosivos.",
        ),
        (
            r"\bhackea(r)? tarjeta(s)? bancaria(s)?\b|\brobar tarjeta(s)?\b|\brobar banco(s)?\b|\bphishing\b.*\bclave(s)?\b",
            "ilegal",
            "Solicitud de hacking malicioso o fraude financiero.",
        ),
        (
            r"\bpublica(r)? su direccion\b|\bpublica(r)? su telefono\b|\bdoxx(ing)?\b|\bfiltra(r)? sus datos\b",
            "acoso",
            "Solicitud de doxxing o exposicion de datos personales.",
        ),
        (
            r"\bextorsion\b|\bchantaje\b|\bestafa grave\b",
            "ilegal",
            "Contenido relacionado con extorsion o estafa grave.",
        ),
        (
            r"\btrata de personas\b|\btrafico de personas\b|\btraficar personas\b|\bventa de personas\b"
            r"|\b(vendo|vender|compro|comprar|trafico|traficar)\b.*\b(nino(s)?|nina(s)?|menor(es)?|persona(s)?|humano(s)?|bebe(s)?|mujer(es)?|hombre(s)?)\b",
            "ilegal",
            "Contenido relacionado con trata, venta o trafico de personas.",
        ),
        (
            r"\btrafico animal\b|\btraficar animal(es)?\b|\bventa ilegal de animal(es)?\b"
            r"|\b(vendo|vender|compro|comprar|trafico|traficar)\b.*\b(condor(es)?|fauna silvestre|animal(es)? silvestre(s)?|especie(s)? protegida(s)?)\b",
            "ilegal",
            "Contenido relacionado con trafico o venta ilegal de fauna.",
        ),
        (
            r"(https?://\S+\s*){3,}",
            "spam",
            "Spam masivo con multiples enlaces.",
        ),
    ]

    for patron, categoria, razon in reglas_bloqueo:
        if re.search(patron, texto):
            return "bloqueado", 0.93, categoria, razon, "bloquear", True

    reglas_revision = [
        (r"\bidiota\b|\bestupido\b|\bimbecil\b", "toxicidad", "Lenguaje toxico leve o posible ataque personal."),
        (r"\bcallate\b|\bno sabes nada\b", "acoso", "Interaccion agresiva que conviene revisar."),
        (r"(https?://\S+\s*){1,2}", "spam", "Posible spam o promocion externa."),
        ("|".join(_patrones_venta_general()), "otro", "Contenido comercial o de venta que requiere inspeccion humana."),
    ]

    for patron, categoria, razon in reglas_revision:
        if re.search(patron, texto):
            return "revision", 0.58, categoria, razon, "enviar_revision", True

    return None


def _riesgo_foro_estricto(texto):
    if not texto:
        return None

    reglas = [
        (
            r"\bforo\b.*\b(matar|asesinar|terrorismo|drogas|armas|odio racial|trata)\b"
            r"|\bcomo matar\b|\bcomo asesinar\b|\bmatar a mis profesor(es)?\b|\basesinar a mis profesor(es)?\b",
            "violencia",
            "Un foro no puede organizar, promover o facilitar violencia real.",
        ),
        (
            r"\b(vendo|vender|compro|comprar|trafico|traficar)\b.*\b(mujer(es)?|nino(s)?|nina(s)?|menor(es)?|persona(s)?|humano(s)?|bebe(s)?)\b"
            r"|\btrata de personas\b|\btrafico de personas\b|\bventa de personas\b",
            "ilegal",
            "Un foro no puede facilitar trata, venta o explotacion humana.",
        ),
        (
            r"\bodio racial\b|\bexterminar\b.*\b(raza|religion|grupo|personas)\b|\bterrorismo\b|\bgrupo terrorista\b",
            "odio",
            "Un foro no puede fomentar odio extremo o terrorismo.",
        ),
    ]

    for patron, categoria, razon in reglas:
        if re.search(patron, texto):
            return "bloqueado", 0.96, categoria, razon, "bloquear", True

    return None


def _categoria_benigna(texto):
    if _contiene(texto, _patrones_tecnologia()):
        return "tecnologia", True

    if _contiene(texto, _patrones_educativos()):
        return "educativo", True

    if _contiene(texto, _patrones_ocio()):
        return "ocio", False

    if _contiene(texto, _patrones_conversacionales()):
        return "conversacional", False

    return "conversacional", False


def _texto_moderacion(payload):
    payload = payload or {}
    contenido = payload.get("contenido") or {}
    contexto = payload.get("contexto") or {}

    return _normalizar(
        " ".join(
            [
                contenido.get("titulo", ""),
                contenido.get("nombre", ""),
                contenido.get("texto", ""),
                contexto.get("categoria", ""),
                contexto.get("foro", ""),
            ]
        )
    )


def moderacion_respaldo(payload):
    texto = _texto_moderacion(payload)
    alerta_seguridad = _detectar_alerta_seguridad(texto)

    if not texto:
        return _respuesta(
            estado="revision",
            riesgo=0.35,
            categoria="otro",
            razon="No hay suficiente contenido para analizar con confianza.",
            accion="enviar_revision",
            valor_educativo=False,
            revision=True,
            alerta_seguridad=alerta_seguridad,
        )

    riesgo_real = _riesgo_real_detectado(texto)
    if (payload.get("tipo_contenido") == "foro") and texto:
        riesgo_real = _riesgo_foro_estricto(texto) or riesgo_real
    if riesgo_real:
        estado, riesgo, categoria, razon, accion, revision = riesgo_real
        return _respuesta(estado, riesgo, categoria, razon, accion, False, revision, alerta_seguridad)

    categoria_benigna, educativo = _categoria_benigna(texto)

    riesgo_base = 0.08 if educativo else 0.15
    if alerta_seguridad["requiere_alerta"]:
        riesgo_base = max(riesgo_base, 0.38)

    return _respuesta(
        estado="permitido",
        riesgo=riesgo_base,
        categoria=categoria_benigna,
        razon="Contenido permitido; no se detectan senales claras de dano real.",
        accion="publicar",
        valor_educativo=educativo,
        revision=False,
        alerta_seguridad=alerta_seguridad,
    )


def normalizar_resultado_moderacion(data, payload=None, safety_metadata=None):
    if not isinstance(data, dict):
        return moderacion_respaldo({"contenido": {"texto": ""}})

    estado = str(data.get("estado", "revision")).lower().strip()
    if estado not in ESTADOS_VALIDOS:
        estado = "revision"

    try:
        riesgo = float(data.get("riesgo", 0.5))
    except (TypeError, ValueError):
        riesgo = 0.5
    riesgo = max(0.0, min(1.0, riesgo))

    categoria = str(data.get("categoria") or "otro").lower().strip()
    if categoria not in CATEGORIAS_VALIDAS:
        categoria = "otro"

    valor_educativo = bool(data.get("valor_educativo", False))
    requiere_revision = bool(data.get("requiere_revision_humana", estado == "revision"))

    texto = _texto_moderacion(payload or {})
    alerta_seguridad = _detectar_alerta_seguridad(texto)
    safety_summary = _resumen_safety(safety_metadata)
    tiene_riesgo_real = bool(texto and _contiene(texto, _patrones_riesgo_real()))
    categoria_benigna, educativo_inferido = _categoria_benigna(texto) if texto else ("conversacional", False)
    riesgo_real = _riesgo_real_detectado(texto) if texto else None
    if (payload or {}).get("tipo_contenido") == "foro" and texto:
        riesgo_real = _riesgo_foro_estricto(texto) or riesgo_real

    if riesgo_real:
        estado_detectado, riesgo_detectado, categoria_detectada, razon_detectada, accion_detectada, revision_detectada = riesgo_real
        estado = estado_detectado
        riesgo = max(riesgo, riesgo_detectado)
        categoria = categoria_detectada
        valor_educativo = False
        requiere_revision = revision_detectada
        data = {**data, "razon": razon_detectada, "accion_recomendada": accion_detectada}

    if (
        estado == "revision"
        and not tiene_riesgo_real
        and riesgo <= 0.55
        and (categoria in {"otro", "conversacional", "ocio", "educativo", "tecnologia"} or texto)
    ):
        estado = "permitido"
        riesgo = min(riesgo, 0.22)
        categoria = categoria if categoria in {"educativo", "conversacional", "ocio", "tecnologia"} else categoria_benigna
        valor_educativo = valor_educativo or educativo_inferido or categoria in {"educativo", "tecnologia"}
        requiere_revision = False

    if estado == "bloqueado" and not tiene_riesgo_real and categoria in {"violencia", "ilegal", "otro"}:
        if texto and (_contiene(texto, _patrones_educativos()) or _contiene(texto, _patrones_tecnologia())):
            estado = "permitido"
            riesgo = min(riesgo, 0.25)
            categoria = categoria_benigna
            valor_educativo = True
            requiere_revision = False

    accion = data.get("accion_recomendada")
    if not accion:
        accion = "publicar" if estado == "permitido" else "bloquear" if estado == "bloqueado" else "enviar_revision"
    if estado == "permitido":
        accion = "publicar"
    elif estado == "bloqueado":
        accion = "bloquear"
    elif accion == "publicar":
        accion = "enviar_revision"

    if alerta_seguridad["requiere_alerta"]:
        riesgo = max(riesgo, {"riesgo_medio": 0.38, "riesgo_alto": 0.72, "riesgo_critico": 0.93}[alerta_seguridad["nivel"]])
        if alerta_seguridad["tipo"] == "autolesion":
            categoria = "autolesion"

    estado, riesgo, categoria, alerta_seguridad = _aplicar_safety_oficial(
        estado,
        riesgo,
        categoria,
        alerta_seguridad,
        safety_summary,
    )
    requiere_revision = estado == "revision"
    if estado == "permitido":
        accion = "publicar"
    elif estado == "bloqueado":
        accion = "bloquear"
    else:
        accion = "enviar_revision"

    return {
        "estado": estado,
        "riesgo": round(riesgo, 2),
        "categoria": categoria,
        "razon": _limpiar_texto(data.get("razon")) or "Analisis de moderacion completado.",
        "accion_recomendada": accion,
        "valor_educativo": valor_educativo,
        "requiere_revision_humana": requiere_revision,
        "alerta_seguridad": alerta_seguridad,
        "safety_ratings": safety_summary,
    }


def _respuesta(estado, riesgo, categoria, razon, accion, valor_educativo, revision, alerta_seguridad=None):
    return {
        "estado": estado,
        "riesgo": round(float(riesgo), 2),
        "categoria": categoria,
        "razon": razon,
        "accion_recomendada": accion,
        "valor_educativo": bool(valor_educativo),
        "requiere_revision_humana": bool(revision),
        "alerta_seguridad": alerta_seguridad
        if alerta_seguridad and alerta_seguridad.get("nivel") in NIVELES_ALERTA_SEGURIDAD
        else {
            "requiere_alerta": False,
            "nivel": "ninguno",
            "tipo": "ninguno",
            "razon": "",
        },
    }
