# CapstoneProjectIA
Final project of the postgraduate course in artificial intelligence, based on a RAG to provide curriculum information when searching or asking about certain careers

# Sistema RAG de Orientación Vocacional

## 📋 Tabla de Contenidos

- [Características](#características)
- [Arquitectura del Sistema](#arquitectura-del-sistema)
- [Requisitos del Sistema](#requisitos-del-sistema)
- [Instalación y Configuración](#instalación-y-configuración)
  - [Parte 1: Web Scraping](#parte-1-web-scraping)
  - [Parte 2: Sistema RAG](#parte-2-sistema-rag)
  - [Parte 3: Deploy Local](#parte-3-deploy-local)
- [Uso del Sistema](#uso-del-sistema)
- [Evaluación del Sistema](#evaluación-del-sistema)
- [Estructura del Proyecto](#estructura-del-proyecto)
- [Tecnologías Utilizadas](#tecnologías-utilizadas)
- [Contribución](#contribución)
- [Licencia](#licencia)

---

## Características

- **Web Scraping Automatizado**: Extracción de pensums desde el sitio web de Universidad Galileo
- **Base de Datos Vectorial**: Búsqueda semántica con FAISS para recuperación
- **Generación con LLM**: Respuestas contextualizadas usando Llama 3.3 70B via Groq API
- **Filtrado por Carreras**: Sistema de recomendación personalizado
- **Interfaz Web**: Chat interactivo PHP para consultas en tiempo real
- **Evaluación Automatizada**: Métricas con DeepEval

---

## Arquitectura del Sistema

```
┌─────────────────┐
│  Web Scraping   │
│  (BeautifulSoup)│
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Chunking       │
│  (Por ciclos)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Embeddings     │
│  (Sentence-T.)  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  FAISS Index    │
│  (Vector DB)    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  RAG System     │
│  (Retrieval +   │
│   Generation)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  LLM (Groq)     │
│  Llama 3.3 70B  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Web Interface  │
│  (PHP + JS)     │
└─────────────────┘
```

---

## Requisitos del Sistema

### Software Base
- **Python**: 3.8 o superior
- **PHP**: 7.4 o superior
- **Servidor Web**: Apache/Nginx con PHP
- **Base de Datos**: SQL Server (para gestión de usuarios)

### Dependencias Python
```txt
sentence-transformers>=2.2.0
faiss-cpu>=1.7.4
groq>=0.4.0
numpy>=1.21.0
requests>=2.28.0
beautifulsoup4>=4.11.0
deepeval>=0.20.0
```

---

## 🚀 Instalación y Configuración

### Parte 1: Web Scraping

#### 1.1. Configuración del Entorno

```bash
# Instalar dependencias básicas
pip install requests beautifulsoup4 lxml
```

#### 1.2. Ejecutar Web Scraping

**Usando Google Colab**

1. Abrir el notebook `web_scraping.ipynb` en Google Colab
2. Ejecutar las celdas en orden
3. Descargar el archivo `galileo_pensums.zip` generado

#### 1.3. Estructura de Datos Extraídos

Los pensums se guardan en formato JSON:

```json
{
  "carrera": "INGENIERÍA DE SISTEMAS",
  "universidad": "Galileo",
  "url": "https://...",
  "ciclos": [
    {
      "nombre": "primer año - 1er ciclo",
      "materias": [
        {
          "codigo": "MA201",
          "nombre": "MATEMÁTICA I",
          "creditos": 8
        }
      ],
      "total_creditos": 28
    }
  ],
  "materias_totales": 48,
  "creditos_totales": 227
}
```

---

### Parte 2: Sistema RAG

#### 2.1. Instalar Dependencias del RAG

```txt
sentence-transformers
faiss-cpu
groq
numpy
deepeval
```

#### 2.2. Obtener API Key de Groq

1. **Crear cuenta en Groq**:
   - Visitar: https://console.groq.com
   - Registrarse con email
   - Verificar cuenta

2. **Generar API Key**:
   - Ir a: https://console.groq.com/keys
   - Click en "Create API Key"
   - Copiar la clave (formato: `gsk_...`)

3. **Configurar en el código**:

```python
# En rag_api.py
class Config:
    GROQ_API_KEY = 'gsk_TU_API_KEY_AQUI'
```

O usar variables de entorno:
```bash
# Windows (CMD)
set GROQ_API_KEY=gsk_TU_API_KEY_AQUI
```

#### 2.3. Preparar Datos

```bash
# Descomprimir pensums extraídos
unzip galileo_pensums.zip -d data/raw/galileo/pensums/

# Verificar estructura
ls data/raw/galileo/pensums/
# Debería mostrar archivos .json y .txt
```

#### 2.4. Construir Base Vectorial

**Usando Colab**

```python
# En Google Colab
!unzip galileo_pensums.zip

# Construir base vectorial
construir_rag_completo()

# Descargar base vectorial
from google.colab import files
import shutil

shutil.make_archive('vectordb', 'zip', 'data/vectordb')
files.download('vectordb.zip')
```

#### 2.5. Probar Sistema RAG

```python
from rag_system import consultar_rag

# Prueba simple
resultado = consultar_rag(
    query="¿Qué materias de matemáticas hay?",
    carreras_recomendadas=None
)

print(resultado['respuesta'])
```

---

### Parte 3: Deploy Local

#### 3.1. Configurar Servidor Web

**Opción A: XAMPP (Windows/Mac/Linux)**

1. Descargar XAMPP: https://www.apachefriends.org/
2. Instalar y ejecutar Apache y MySQL
3. Copiar archivos del proyecto a `htdocs/`:

```bash
# Windows
xcopy /E /I proyecto C:\xampp\htdocs\orientacion-vocacional

# Linux/Mac
cp -r proyecto /opt/lampp/htdocs/orientacion-vocacional
```

**Opción B: PHP Built-in Server (Desarrollo)**

```bash
cd proyecto/web
php -S localhost:8000
```

#### 3.2. Estructura de Archivos en el Servidor

```
htdocs/orientacion-vocacional/
├── data/
│   └── vectordb/
│       ├── faiss_index.bin
│       ├── chunks_metadata.json
│       └── config.json
├── rag_api.py
├── rag_query.php
├── consultas_rag.php
├── requirements.txt
└── database/
    └── config.php
```

#### 3.3. Configurar Base de Datos Vectorial

```bash
# Descomprimir base vectorial en servidor
cd htdocs/orientacion-vocacional
unzip vectordb.zip -d data/
```

Verificar estructura:
```bash
ls data/vectordb/
# Debe mostrar: faiss_index.bin, chunks_metadata.json, config.json
```

#### 3.4. Configurar PHP

Editar `rag_query.php`:

```php
// Ruta del script Python
$python_script = __DIR__ . '/rag_api.py';

// Comando Python (ajustar según tu sistema)
$python_cmd = 'python3'; // o 'python' o 'py'
```

#### 3.5. Configurar Permisos (Linux/Mac)

```bash
chmod +x rag_api.py
chmod 755 data/vectordb/
chmod 644 data/vectordb/*
```

#### 3.6. Probar Integración PHP-Python

Crear archivo `test_integration.php`:

```php
<?php
// Test de conexión Python
$command = 'python3 rag_api.py';
$output = shell_exec($command . ' 2>&1');
echo "<pre>$output</pre>";
?>
```

Visitar: `http://localhost/orientacion-vocacional/test_integration.php`

#### 3.7. Configurar Base de Datos SQL Server

Editar `database/config.php`:

```php
<?php
$serverName = "localhost";
$connectionOptions = array(
    "Database" => "orientacion_vocacional",
    "Uid" => "usuario",
    "PWD" => "contraseña"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>
```


## Uso del Sistema

### Desde la Interfaz Web

1. Navegar a "Recomendación" → "Consultas RAG"
2. Escribir pregunta en el chat
3. El sistema filtrará automáticamente por tus carreras recomendadas

### Consultas Sugeridas

```
- ¿Qué materias de matemáticas tiene el pensum?
- ¿Cuántos créditos tiene la carrera?
- ¿En qué ciclo se ve programación?
- Compara las tres carreras recomendadas
- ¿Qué materias son comunes entre mis opciones?
```

### Desde Python en collab

```python

# Consulta con filtro de carreras
resultado = consultar_rag(
    query="¿Qué materias de programación hay?",
    carreras_recomendadas=[
        "INGENIERÍA DE SISTEMAS",
        "INGENIERÍA EN ELECTRÓNICA"
    ]
)

print(f"Respuesta: {resultado['respuesta']}")
print(f"Fuentes: {resultado['fuentes']}")
print(f"Chunks utilizados: {resultado['chunks_utilizados']}")
```

---

##  Evaluación del Sistema

### Ejecutar Evaluación con DeepEval

```python en collab

# Ejecutar evaluación completa
resultados = evaluar_rag_con_deepeval()
```

### Métricas Evaluadas

1. **Answer Relevancy**: Relevancia de la respuesta (threshold: 0.7)
2. **Faithfulness**: Fidelidad al contexto recuperado (threshold: 0.7)
3. **Contextual Precision**: Precisión del contexto (threshold: 0.7)
4. **Contextual Recall**: Completitud del contexto (threshold: 0.7)

### Agregar Casos de Prueba Personalizados

Editar en `rag_system.py`:

```python
test_cases = [
    {
        "input": "Tu pregunta aquí",
        "expected_output": "Respuesta esperada",
        "carreras": ["Carrera 1", "Carrera 2"]
    },
    # Agregar más casos...
]
```

---

##  Estructura del Proyecto

```
orientacion-vocacional/
│
├── data/
│   ├── raw/
│   │   └── galileo/
│   │       └── pensums/           # Pensums extraídos (JSON/TXT)
│   │
│   ├── processed/                 # Datos procesados
│   │
│   └── vectordb/                  # Base de datos vectorial
│       ├── faiss_index.bin        # Índice FAISS
│       ├── chunks_metadata.json   # Metadatos de chunks
│       └── config.json            # Configuración
│
├── scraping/
│   └── scraping_galileo.py        # Script de web scraping
│
├── rag/
│   └── rag_system.py              # Sistema RAG completo
│
├── web/
│   ├── rag_api.py                 # API Python para PHP
│   ├── rag_query.php              # Endpoint PHP
│   ├── consultas_rag.php          # Interfaz de usuario
│   └── database/
│       └── config.php             # Configuración BD
│
├── tests/
│   └── test_rag.py                # Tests unitarios
│
├── notebooks/
│   ├── web_scraping.ipynb         # Notebook scraping
│   └── rag_construction.ipynb     # Notebook RAG
│
├── requirements.txt               # Dependencias Python
├── README.md                      # Este archivo
└── DOCUMENTATION.md               # Documentación técnica
```

---

##  Tecnologías Utilizadas

### Backend
- **Python 3.8+**: Lenguaje principal
- **FAISS**: Base de datos vectorial
- **Sentence-Transformers**: Modelo de embeddings
- **Groq API**: Servicio LLM (Llama 3.3 70B)
- **BeautifulSoup4**: Web scraping
- **DeepEval**: Evaluación de calidad

### Frontend
- **PHP 7.4+**: Servidor backend
- **JavaScript (Vanilla)**: Interactividad
- **Bootstrap 5**: Framework CSS
- **Bootstrap Icons**: Iconografía

### Base de Datos
- **FAISS**: Búsqueda vectorial (local)
- **SQL Server**: Gestión de usuarios y recomendaciones

---

##  Solución de Problemas

### Error: "GROQ_API_KEY no configurada"
```bash
# Verificar variable de entorno
echo $GROQ_API_KEY  # Linux/Mac
echo %GROQ_API_KEY%  # Windows

# Configurar directamente en código
# Editar rag_api.py línea ~40
```

### Error: "Base vectorial no encontrada"
```bash
# Verificar estructura de archivos
ls -la data/vectordb/

# Reconstruir base vectorial
python -c "from rag_system import construir_rag_completo; construir_rag_completo()"
```

### Error: "ModuleNotFoundError"
```bash
# Reinstalar dependencias
pip install --upgrade -r requirements.txt

# Verificar instalación
pip list | grep sentence-transformers
pip list | grep faiss
```

### Error: PHP no ejecuta Python
```bash
# Verificar comando Python
which python3  # Linux/Mac
where python   # Windows

# Dar permisos (Linux/Mac)
chmod +x rag_api.py

# Verificar desde PHP
php -r "system('python3 --version');"
```


---

## 👥 Autores

- **Jose Javier Barrios** - *21000478*

---
