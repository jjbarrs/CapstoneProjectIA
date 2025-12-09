#!/usr/bin/env python3
"""
Test RAG - Script de prueba independiente
Permite probar el sistema RAG sin PHP
"""

import sys
import json
import os
from pathlib import Path

print("=" * 80)
print("INICIANDO PRUEBA DEL SISTEMA RAG")
print("=" * 80)

# ============================================================================
# PASO 1: Verificar imports
# ============================================================================
print("\n1️⃣ Verificando dependencias...")

try:
    from sentence_transformers import SentenceTransformer
    print("   ✅ sentence-transformers")
except ImportError as e:
    print(f"   ❌ sentence-transformers: {e}")
    print("   Instalar con: pip install sentence-transformers")
    sys.exit(1)

try:
    import faiss
    print("   ✅ faiss")
except ImportError as e:
    print(f"   ❌ faiss: {e}")
    print("   Instalar con: pip install faiss-cpu")
    sys.exit(1)

try:
    import numpy as np
    print("   ✅ numpy")
except ImportError as e:
    print(f"   ❌ numpy: {e}")
    sys.exit(1)

try:
    from groq import Groq
    print("   ✅ groq")
except ImportError as e:
    print(f"   ❌ groq: {e}")
    print("   Instalar con: pip install groq")
    sys.exit(1)

# ============================================================================
# PASO 2: Verificar rutas y archivos
# ============================================================================
print("\n2️⃣ Verificando archivos de la base vectorial...")

# AJUSTA ESTA RUTA según donde tengas tu carpeta data/vectordb
VECTORDB_DIR = Path("data/vectordb")

print(f"   Buscando en: {VECTORDB_DIR}")

index_path = VECTORDB_DIR / "faiss_index.bin"
metadata_path = VECTORDB_DIR / "chunks_metadata.json"
config_path = VECTORDB_DIR / "config.json"

if not VECTORDB_DIR.exists():
    print(f"   ❌ El directorio no existe: {VECTORDB_DIR}")
    print(f"   Crea la estructura: mkdir -p {VECTORDB_DIR}")
    sys.exit(1)
else:
    print(f"   ✅ Directorio existe")

if not index_path.exists():
    print(f"   ❌ No se encuentra: faiss_index.bin")
    sys.exit(1)
else:
    print(f"   ✅ faiss_index.bin encontrado ({index_path.stat().st_size} bytes)")

if not metadata_path.exists():
    print(f"   ❌ No se encuentra: chunks_metadata.json")
    sys.exit(1)
else:
    print(f"   ✅ chunks_metadata.json encontrado ({metadata_path.stat().st_size} bytes)")

# ============================================================================
# PASO 3: Cargar base vectorial
# ============================================================================
print("\n3️⃣ Cargando base vectorial...")

try:
    index = faiss.read_index(str(index_path))
    print(f"   ✅ Índice FAISS cargado: {index.ntotal} vectores")
except Exception as e:
    print(f"   ❌ Error al cargar índice FAISS: {e}")
    sys.exit(1)

try:
    with open(metadata_path, 'r', encoding='utf-8') as f:
        chunks_metadata = json.load(f)
    print(f"   ✅ Metadatos cargados: {len(chunks_metadata)} chunks")
except Exception as e:
    print(f"   ❌ Error al cargar metadatos: {e}")
    sys.exit(1)

# Verificar carreras disponibles
print("\n   📚 Carreras disponibles:")
carreras = set()
for chunk in chunks_metadata[:10]:  # Primeros 10 chunks
    carrera = chunk["metadata"]["carrera"]
    if carrera not in carreras:
        carreras.add(carrera)
        print(f"      - {carrera}")

if len(chunks_metadata) > 10:
    print(f"      ... y más")

# ============================================================================
# PASO 4: Cargar modelo de embeddings
# ============================================================================
print("\n4️⃣ Cargando modelo de embeddings...")

try:
    model = SentenceTransformer("paraphrase-multilingual-MiniLM-L12-v2")
    print("   ✅ Modelo cargado correctamente")
except Exception as e:
    print(f"   ❌ Error al cargar modelo: {e}")
    sys.exit(1)

# ============================================================================
# PASO 5: Verificar API Key de Groq
# ============================================================================
print("\n5️⃣ Verificando API Key de Groq...")

# AJUSTA AQUÍ TU API KEY
# Opción 1: Variable de entorno
GROQ_API_KEY = os.environ.get('GROQ_API_KEY')

# Opción 2: Hardcoded (SOLO PARA PRUEBAS)
if not GROQ_API_KEY:
    GROQ_API_KEY = 'tu_api_key_aqui'

if not GROQ_API_KEY or GROQ_API_KEY == 'tu_api_key_aqui':
    print("   ❌ API Key no configurada")
    print("   Configura con: export GROQ_API_KEY='tu_key'")
    print("   O edita la línea 123 de este script")
    sys.exit(1)
else:
    print(f"   ✅ API Key configurada (primeros 10 chars): {GROQ_API_KEY[:10]}...")

# ============================================================================
# PASO 6: Prueba de búsqueda
# ============================================================================
print("\n6️⃣ Probando búsqueda semántica...")

query_test = "¿Qué materias de matemáticas hay?"
print(f"   Query de prueba: '{query_test}'")

try:
    query_embedding = model.encode([query_test], convert_to_numpy=True)[0]
    print("   ✅ Embedding generado")
    
    distances, indices = index.search(
        np.array([query_embedding]).astype('float32'),
        3
    )
    
    print("\n   Top 3 resultados más relevantes:")
    for i, (idx, dist) in enumerate(zip(indices[0], distances[0]), 1):
        if idx < len(chunks_metadata):
            chunk = chunks_metadata[idx]
            print(f"      {i}. {chunk['metadata']['carrera']} - Score: {dist:.4f}")
            print(f"         Ciclo: {chunk['metadata'].get('ciclo', 'N/A')}")
            print(f"         Texto (preview): {chunk['texto'][:80]}...")
    
except Exception as e:
    print(f"   ❌ Error en búsqueda: {e}")
    import traceback
    traceback.print_exc()
    sys.exit(1)

# ============================================================================
# PASO 7: Prueba completa del RAG
# ============================================================================
print("\n7️⃣ Probando generación de respuesta completa...")

# Función para normalizar nombres
import re

def normalizar_nombre_carrera(nombre):
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

# Recuperar chunks
top_k = 3
chunks_relevantes = []
for idx, distance in zip(indices[0][:top_k], distances[0][:top_k]):
    if idx < len(chunks_metadata):
        chunk_data = chunks_metadata[idx].copy()
        chunk_data["score"] = float(distance)
        chunks_relevantes.append(chunk_data)

print(f"   Chunks recuperados: {len(chunks_relevantes)}")

# Construir contexto
contexto_texto = ""
for i, chunk in enumerate(chunks_relevantes, 1):
    metadata = chunk["metadata"]
    contexto_texto += f"\n--- DOCUMENTO {i} ---\n"
    contexto_texto += f"Universidad: Galileo\n"
    contexto_texto += f"Carrera: {metadata['carrera']}\n"
    if metadata.get('ciclo'):
        contexto_texto += f"Ciclo: {metadata['ciclo']}\n"
    contexto_texto += f"\nContenido:\n{chunk['texto']}\n"

# Crear prompt
prompt = f"""Eres un asistente de orientación vocacional especializado en la Universidad Galileo de Guatemala.

Tu rol es responder preguntas sobre pensums de carreras universitarias basándote ÚNICAMENTE en la información proporcionada.

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
{query_test}

RESPUESTA:"""

print("   ✅ Prompt construido")

# Llamar a Groq
print("\n8️⃣ Llamando a Groq API...")

try:
    from groq import Groq
    client = Groq(api_key=GROQ_API_KEY)
    
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
        model="llama-3.3-70b-versatile",
        temperature=0.2,
        max_tokens=2000
    )
    
    respuesta = chat_completion.choices[0].message.content
    
    print("   ✅ Respuesta generada")
    print("\n" + "=" * 80)
    print("RESPUESTA DEL SISTEMA RAG:")
    print("=" * 80)
    print(respuesta)
    print("=" * 80)
    
    # Mostrar fuentes
    print("\n📚 FUENTES CONSULTADAS:")
    fuentes_vistas = set()
    for chunk in chunks_relevantes:
        carrera = chunk["metadata"]["carrera"]
        if carrera not in fuentes_vistas:
            print(f"   - {carrera}")
            fuentes_vistas.add(carrera)
    
    print("\n✅ PRUEBA COMPLETADA EXITOSAMENTE")
    
except Exception as e:
    print(f"   ❌ Error al llamar a Groq: {e}")
    import traceback
    traceback.print_exc()
    sys.exit(1)

# ============================================================================
# PASO 9: Prueba interactiva
# ============================================================================
print("\n" + "=" * 80)
print("MODO INTERACTIVO")
print("=" * 80)
print("Puedes hacer preguntas ahora. Escribe 'salir' para terminar.\n")

def consultar_rag_simple(query):
    """Versión simplificada de consultar_rag para testing"""
    try:
        # Generar embedding
        query_embedding = model.encode([query], convert_to_numpy=True)[0]
        
        # Buscar
        distances, indices = index.search(
            np.array([query_embedding]).astype('float32'),
            4
        )
        
        # Recuperar chunks
        chunks = []
        for idx, dist in zip(indices[0], distances[0]):
            if idx < len(chunks_metadata):
                chunk_data = chunks_metadata[idx].copy()
                chunk_data["score"] = float(dist)
                chunks.append(chunk_data)
        
        if not chunks:
            return "No encontré información relevante."
        
        # Construir contexto
        contexto = ""
        for i, chunk in enumerate(chunks, 1):
            metadata = chunk["metadata"]
            contexto += f"\n--- DOCUMENTO {i} ---\n"
            contexto += f"Carrera: {metadata['carrera']}\n"
            if metadata.get('ciclo'):
                contexto += f"Ciclo: {metadata['ciclo']}\n"
            contexto += f"{chunk['texto']}\n"
        
        # Crear prompt
        prompt_simple = f"""Eres un asistente de orientación vocacional.

Información disponible:
{contexto}

Pregunta: {query}

Respuesta (clara y concisa):"""
        
        # Llamar Groq
        chat_completion = client.chat.completions.create(
            messages=[{"role": "user", "content": prompt_simple}],
            model="llama-3.3-70b-versatile",
            temperature=0.2,
            max_tokens=1500
        )
        
        return chat_completion.choices[0].message.content
        
    except Exception as e:
        return f"Error: {str(e)}"

while True:
    try:
        pregunta = input("\n💬 Tu pregunta: ").strip()
        
        if pregunta.lower() in ['salir', 'exit', 'quit']:
            print("👋 ¡Hasta luego!")
            break
        
        if not pregunta:
            continue
        
        print("\n🤖 Procesando...")
        respuesta = consultar_rag_simple(pregunta)
        print(f"\n💡 Respuesta:\n{respuesta}")
        
    except KeyboardInterrupt:
        print("\n\n👋 ¡Hasta luego!")
        break
    except Exception as e:
        print(f"\n❌ Error: {e}")