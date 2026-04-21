import re
import unicodedata


STOPWORDS = {
    "a", "al", "algo", "alguna", "alguno", "ante", "ayuda", "ayudame", "ayudarias", "cual", "como",
    "con", "contra", "crear", "de", "definir", "del", "donde", "el", "ella", "ellas",
    "ellos", "en", "es", "esa", "ese", "eso", "esta", "este", "esto", "foro", "foros",
    "fue", "hablame", "la", "las", "lo", "los", "mas", "me", "mi", "mis", "para",
    "por", "que", "quiero", "recomienda", "recomiendame", "se", "ser", "si", "sobre",
    "su", "sus", "tema", "temas", "titulo", "tu", "un", "una", "uno", "y",
}


def _normalizar_texto(valor):
    texto = unicodedata.normalize("NFKD", str(valor or ""))
    texto = "".join(caracter for caracter in texto if not unicodedata.combining(caracter))
    return " ".join(texto.lower().split())


def _tokenizar(texto):
    texto_normalizado = _normalizar_texto(texto)
    tokens = re.findall(r"[a-z0-9]+", texto_normalizado)
    return [token for token in tokens if len(token) > 2 and token not in STOPWORDS]


def _texto_foro(foro):
    return " ".join(
        [
            str(foro.get("titulo", "")),
            str(foro.get("descripcion", "")),
            str(foro.get("categoria", "")),
        ]
    )


def _historial_texto(contexto):
    return " ".join(item.get("texto", "") for item in (contexto or {}).get("historial", []))


def _extraer_tema(contexto):
    mensaje = (contexto or {}).get("mensaje", "")
    historial = _historial_texto(contexto)
    texto_normalizado = _normalizar_texto(f"{historial} {mensaje}")

    coincidencia = re.search(r"\bsobre\s+(.+?)(?:\?|\.|,|$)", texto_normalizado)
    if coincidencia:
        tokens_tema = _tokenizar(coincidencia.group(1))
        if tokens_tema:
            return " ".join(tokens_tema[:5])

    tokens = _tokenizar(f"{historial} {mensaje}")

    if not tokens:
        return "tu tema principal"

    tema_tokens = []
    for token in tokens:
        if token not in tema_tokens:
            tema_tokens.append(token)
        if len(tema_tokens) == 5:
            break

    return " ".join(tema_tokens)


def es_peticion_de_foros(contexto):
    mensaje = _normalizar_texto((contexto or {}).get("mensaje"))
    historial = _normalizar_texto(_historial_texto(contexto))

    es_directa = any(
        patron in mensaje
        for patron in [
            "foro",
            "foros",
            "comunidad",
            "comunidades",
            "crear un foro",
            "crear foro",
        ]
    )
    if es_directa:
        return True

    # Solo consideramos seguimiento de foros para mensajes cortos de edicion/estructura.
    es_followup_corto = any(patron in mensaje for patron in ["titulo", "descripcion", "categoria", "publico objetivo"])
    hubo_foro_antes = any(patron in historial for patron in ["foro", "foros", "comunidad", "crear foro", "crear un foro"])
    return es_followup_corto and hubo_foro_antes


def clasificar_peticion_foro(contexto):
    mensaje = _normalizar_texto((contexto or {}).get("mensaje"))
    historial = _normalizar_texto(_historial_texto(contexto))

    if any(expresion in mensaje for expresion in ["crear un foro", "crear foro", "quiero crear"]):
        return "crear"
    if "titulo" in mensaje and any(expresion in historial for expresion in ["crear un foro", "crear foro"]):
        return "crear"
    if any(expresion in mensaje for expresion in ["recomienda", "recomiendame", "sugiere"]):
        return "recomendar"
    return "orientar"


def recomendar_foros(contexto, foros, limite=3):
    consulta = (contexto or {}).get("mensaje", "")
    intereses = (contexto or {}).get("intereses") or []
    historial = _historial_texto(contexto)
    tokens = _tokenizar(" ".join([consulta, historial, " ".join(map(str, intereses))]))

    puntuados = []
    for foro in foros:
        texto = _normalizar_texto(_texto_foro(foro))
        score = 0

        for token in tokens:
            if token in texto:
                score += 3 if token in _normalizar_texto(foro.get("titulo", "")) else 1

        if foro.get("categoria") and _normalizar_texto(foro["categoria"]) in _normalizar_texto(consulta):
            score += 2

        if score > 0:
            puntuados.append((score, foro))

    puntuados.sort(key=lambda item: item[0], reverse=True)
    recomendados = [foro for _, foro in puntuados[:limite]]

    if recomendados:
        return recomendados

    return foros[:limite]


def construir_guia_creacion_foro(contexto, foros=None):
    tema = _extraer_tema(contexto)
    tema_para_titulo = " ".join(token for token in tema.split() if token not in {"creencia", "creencias"})
    if not tema_para_titulo:
        tema_para_titulo = tema
    titulo_base = tema_para_titulo.title() if tema_para_titulo != "tu tema principal" else "Tema Central"
    recomendados = recomendar_foros(contexto, foros or [], limite=2)
    nota_foros = ""

    if recomendados:
        nombres = ", ".join(foro["titulo"] for foro in recomendados if foro.get("titulo"))
        nota_foros = f" Antes de publicarlo, revisa si se cruza con: {nombres}."

    return (
        f"Para ese foro, un titulo fuerte seria: \"{titulo_base}: creencias, cultura y legado\". "
        f"Tambien podrias usar \"Debate sobre {tema}: mitos, sociedad y religion\" o "
        f"\"{titulo_base} en contexto: ideas, dioses y vida cotidiana\". "
        "Mi recomendacion es elegir el primero si quieres que suene academico y claro, porque anuncia tema, enfoque y valor del debate."
        f"{nota_foros}"
    )


def construir_respuesta_foros(contexto, foros):
    tipo_peticion = clasificar_peticion_foro(contexto)
    recomendados = recomendar_foros(contexto, foros)

    if tipo_peticion == "crear":
        return construir_guia_creacion_foro(contexto, foros)

    if not recomendados:
        return (
            "No pude cargar los foros existentes ahora mismo, pero puedo ayudarte igual: dime el tema, "
            "el publico objetivo y si lo quieres academico o conversacional, y te propongo titulo, descripcion y categoria."
        )

    encabezado = "Estos foros existentes te pueden servir mejor para esa peticion:"
    lista = " ".join(
        [
            f"{indice + 1}. {foro['titulo']}"
            + (f" en {foro['categoria']}" if foro.get("categoria") else "")
            + (f": {foro['descripcion']}" if foro.get("descripcion") else ".")
            for indice, foro in enumerate(recomendados)
        ]
    )
    cierre = "Si quieres, tambien te digo cual encaja mejor segun tu tema exacto."
    return f"{encabezado} {lista} {cierre}"
