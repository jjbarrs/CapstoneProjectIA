"""
RAG API - Sistema de consultas vocacionales
Interfaz Python para el sistema RAG de orientacion vocacional
"""

import sys
import json
import os
from pathlib import Path

try:
    from sentence_transformers import SentenceTransformer
    import faiss
    import numpy as np
    from groq import Groq
except ImportError as e:
    print(json.dumps({
        'success': False,
        'error': f'Error de importación: {str(e)}. Instala: pip install sentence-transformers faiss-cpu groq'
    }))
    sys.exit(1)

# ============================================================================
# CONFIGURACIÓN
# ============================================================================

class Config:
    """Configuración del sistema RAG"""
    
    VECTORDB_DIR = Path("data/vectordb")
    
    EMBEDDING_MODEL = "paraphrase-multilingual-MiniLM-L12-v2"

    LLM_MODEL = "llama-3.3-70b-versatile"
    MAX_TOKENS = 2000
    TEMPERATURE = 0.2
    TOP_K = 4
    

    GROQ_API_KEY = 'API KEY'


# ============================================================================
# FUNCIONES DEL RAG
# ============================================================================

def cargar_base_vectorial():
    """Carga índice FAISS y metadatos"""
    try:
        index_path = Config.VECTORDB_DIR / "faiss_index.bin"
        metadata_path = Config.VECTORDB_DIR / "chunks_metadata.json"
        
        if not index_path.exists() or not metadata_path.exists():
            return None, None, None, "Base vectorial no encontrada"
        
        index = faiss.read_index(str(index_path))
        
        with open(metadata_path, 'r', encoding='utf-8') as f:
            chunks_metadata = json.load(f)
        
        model = SentenceTransformer(Config.EMBEDDING_MODEL)
        
        return index, chunks_metadata, model, None
        
    except Exception as e:
        return None, None, None, f"Error al cargar base vectorial: {str(e)}"


def normalizar_nombre_carrera(nombre):
    """Normaliza nombre de carrera para comparación"""
    import re
    if not nombre:
        return ""
    
    nombre = nombre.lower()
    nombre = re.sub(r'[áàäâã]', 'a', nombre)
    nombre = re.sub(r'[éèëê]', 'e', nombre)
    nombre = re.sub(r'[íìïî]', 'i', nombre)
    nombre = re.sub(r'[óòöô]', 'o', nombre)
    nombre = re.sub(r'[úùüû]', 'u', nombre)
    nombre = re.sub(r'ñ', 'n', nombre)
    nombre = re.sub(r'[^\w\s]', '', nombre)
    nombre = re.sub(r'\s+', ' ', nombre)
    return nombre.strip()


def buscar_contexto_relevante(query, index, chunks_metadata, embedding_model, 
                              top_k=4, filtro_carreras=None):
    """Busca chunks relevantes para una consulta"""
    try:
        # Generar embedding de la query
        query_embedding = embedding_model.encode([query], convert_to_numpy=True)[0]
        
        # Buscar en FAISS
        k_busqueda = min(top_k * 5 if filtro_carreras else top_k * 2, len(chunks_metadata))
        distances, indices = index.search(
            np.array([query_embedding]).astype('float32'),
            k_busqueda
        )
        
        # Recuperar chunks con scores
        resultados = []
        for idx, distance in zip(indices[0], distances[0]):
            if idx < len(chunks_metadata):
                chunk_data = chunks_metadata[idx].copy()
                chunk_data["score"] = float(distance)
                resultados.append(chunk_data)
        
        # Aplicar filtros si existen
        if filtro_carreras:
            carreras_normalizadas = [normalizar_nombre_carrera(c) for c in filtro_carreras]
            
            resultados_filtrados = []
            for r in resultados:
                carrera_chunk = normalizar_nombre_carrera(r["metadata"]["carrera"])
                
                # Verificar si alguna carrera filtrada coincide
                if any(carrera_norm in carrera_chunk or carrera_chunk in carrera_norm 
                       for carrera_norm in carreras_normalizadas):
                    resultados_filtrados.append(r)
            
            # Si hay resultados filtrados, usarlos; sino, usar sin filtro
            if resultados_filtrados:
                resultados = resultados_filtrados
        
        return resultados[:top_k], None
        
    except Exception as e:
        return [], f"Error en búsqueda: {str(e)}"


def crear_prompt_rag(query, contexto_chunks, carreras_recomendadas=None):
    """Crea el prompt para el LLM"""
    # Construir contexto
    contexto_texto = ""
    for i, chunk in enumerate(contexto_chunks, 1):
        metadata = chunk["metadata"]
        contexto_texto += f"\n--- DOCUMENTO {i} ---\n"
        contexto_texto += f"Universidad: Galileo\n"
        contexto_texto += f"Carrera: {metadata['carrera']}\n"
        if metadata.get('ciclo'):
            contexto_texto += f"Ciclo: {metadata['ciclo']}\n"
        contexto_texto += f"\nContenido:\n{chunk['texto']}\n"
    
    intro = "Eres un asistente de orientación vocacional especializado en la Universidad Galileo de Guatemala.\n\n"
    
    if carreras_recomendadas:
        intro += f"El estudiante ha recibido las siguientes recomendaciones de carreras:\n"
        for carrera in carreras_recomendadas:
            intro += f"  • {carrera}\n"
        intro += "\n"
    
    prompt = f"""{intro}Tu rol es responder preguntas sobre pensums de carreras universitarias basándote ÚNICAMENTE en la información proporcionada.

INFORMACIÓN DISPONIBLE (Pensums de Universidad Galileo):
{contexto_texto}

INSTRUCCIONES IMPORTANTES:
- Responde de forma clara, precisa y conversacional
- Usa SOLO la información del contexto proporcionado
- Si la información no está en el contexto, di claramente: "No encuentro esa información en los pensums disponibles"
- NO inventes materias, créditos o ciclos
- Menciona siempre la carrera y ciclo cuando hables de materias específicas
- Sé amigable y útil

PREGUNTA DEL ESTUDIANTE:
{query}

RESPUESTA:"""
    
    return prompt


def generar_respuesta(query, contexto_chunks, carreras_recomendadas=None):
    """Genera respuesta usando Groq API"""
    try:
        if not Config.GROQ_API_KEY:
            return None, "API Key de Groq no configurada"
        
        # Crear prompt
        prompt = crear_prompt_rag(query, contexto_chunks, carreras_recomendadas)
        
        # Llamar a Groq
        client = Groq(api_key=Config.GROQ_API_KEY)
        
        chat_completion = client.chat.completions.create(
            messages=[
                {
                    "role": "system",
                    "content": "Eres un asistente de orientación vocacional profesional, objetivo y servicial."
                },
                {
                    "role": "user",
                    "content": prompt
                }
            ],
            model=Config.LLM_MODEL,
            temperature=Config.TEMPERATURE,
            max_tokens=Config.MAX_TOKENS
        )
        
        respuesta = chat_completion.choices[0].message.content
        return respuesta, None
        
    except Exception as e:
        return None, f"Error al generar respuesta: {str(e)}"


def consultar_rag(query, carreras_recomendadas=None):
    """Función principal de consulta al RAG"""
    try:
        # Cargar base vectorial
        index, chunks_metadata, embedding_model, error = cargar_base_vectorial()
        if error:
            return {'success': False, 'error': error}
        
        # Buscar contexto relevante
        chunks_relevantes, error = buscar_contexto_relevante(
            query=query,
            index=index,
            chunks_metadata=chunks_metadata,
            embedding_model=embedding_model,
            top_k=Config.TOP_K,
            filtro_carreras=carreras_recomendadas
        )
        
        if error:
            return {'success': False, 'error': error}
        
        if not chunks_relevantes:
            return {
                'success': True,
                'respuesta': 'No encontré información relevante para tu consulta en los pensums disponibles. ¿Podrías reformular tu pregunta?',
                'fuentes': [],
                'chunks_utilizados': 0
            }
        
        # Generar respuesta
        respuesta, error = generar_respuesta(
            query=query,
            contexto_chunks=chunks_relevantes,
            carreras_recomendadas=carreras_recomendadas
        )
        
        if error:
            return {'success': False, 'error': error}
        
        # Compilar fuentes únicas
        fuentes = []
        carreras_vistas = set()
        for chunk in chunks_relevantes:
            carrera = chunk["metadata"]["carrera"]
            if carrera not in carreras_vistas:
                fuentes.append({
                    "universidad": "Galileo",
                    "carrera": carrera,
                    "ciclo": chunk["metadata"].get("ciclo", "N/A")
                })
                carreras_vistas.add(carrera)
        
        return {
            'success': True,
            'respuesta': respuesta,
            'fuentes': fuentes,
            'chunks_utilizados': len(chunks_relevantes)
        }
        
    except Exception as e:
        return {
            'success': False,
            'error': f'Error inesperado: {str(e)}'
        }


# ============================================================================
# MAIN - Interfaz con PHP
# ============================================================================

def main():
    """Función principal que se ejecuta desde PHP"""
    if len(sys.argv) < 3:
        print(json.dumps({
            'success': False,
            'error': 'Uso: python rag_api.py <input_json> <output_json>'
        }))
        sys.exit(1)
    
    input_file = sys.argv[1]
    output_file = sys.argv[2]
    
    try:
        # Leer datos de entrada
        with open(input_file, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        query = data.get('query', '')
        carreras = data.get('carreras_recomendadas', [])
        
        # Validar query
        if not query:
            result = {
                'success': False,
                'error': 'Consulta vacía'
            }
        else:
            # Ejecutar consulta RAG
            result = consultar_rag(query, carreras)
        
        # Guardar resultado
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(result, f, ensure_ascii=False, indent=2)
        
        # También imprimir a stdout para debugging
        print(json.dumps(result, ensure_ascii=False))
        
    except Exception as e:
        error_result = {
            'success': False,
            'error': f'Error en script Python: {str(e)}'
        }
        
        # Intentar guardar el error
        try:
            with open(output_file, 'w', encoding='utf-8') as f:
                json.dump(error_result, f, ensure_ascii=False, indent=2)
        except:
            pass
        
        print(json.dumps(error_result, ensure_ascii=False))
        sys.exit(1)


if __name__ == "__main__":
    main()