<?php
/**
 * RAG Query API - Intermediario entre frontend y Python
 * Recibe consultas del usuario y las envía al sistema RAG de Python
 */

header('Content-Type: application/json');
session_start();

// Verificar sesión
if (!isset($_SESSION['correoEstudiante'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Sesión no válida'
    ]);
    exit();
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit();
}

// Obtener datos JSON del request
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['query']) || empty(trim($input['query']))) {
    echo json_encode([
        'success' => false,
        'error' => 'Consulta vacía'
    ]);
    exit();
}

$query = trim($input['query']);
$carreras = isset($input['carreras']) ? $input['carreras'] : [];
$area = isset($input['area']) ? $input['area'] : '';

// Validar longitud de consulta
if (strlen($query) > 500) {
    echo json_encode([
        'success' => false,
        'error' => 'Consulta demasiado larga (máximo 500 caracteres)'
    ]);
    exit();
}

// Preparar datos para Python
$data = [
    'query' => $query,
    'carreras_recomendadas' => $carreras,
    'area' => $area
];

// Crear archivo temporal con los datos
$temp_input = tempnam(sys_get_temp_dir(), 'rag_input_');
file_put_contents($temp_input, json_encode($data, JSON_UNESCAPED_UNICODE));

// Ruta del script Python
// AJUSTA ESTA RUTA según donde guardes tu script Python
$python_script = 'rag_api.py';

// Verificar que el script existe
if (!file_exists($python_script)) {
    unlink($temp_input);
    echo json_encode([
        'success' => false,
        'error' => 'Script de Python no encontrado en: ' . $python_script
    ]);
    exit();
}

// Archivo de salida temporal
$temp_output = tempnam(sys_get_temp_dir(), 'rag_output_');

// Comando Python
// AJUSTA 'python3' según tu sistema: puede ser 'python', 'py', o 'python3'
$python_cmd = 'python'; // Cambiar según tu sistema

$command = sprintf(
    '%s %s %s %s 2>&1',
    escapeshellcmd($python_cmd),
    escapeshellarg($python_script),
    escapeshellarg($temp_input),
    escapeshellarg($temp_output)
);

// Ejecutar Python
$output = [];
$return_var = 0;
exec($command, $output, $return_var);

// Leer resultado
$result = null;
if (file_exists($temp_output)) {
    $result_content = file_get_contents($temp_output);
    $result = json_decode($result_content, true);
}

// Limpiar archivos temporales
@unlink($temp_input);
@unlink($temp_output);

// Verificar resultado
if ($result && isset($result['success'])) {
    echo json_encode($result);
} else {
    // Log de error para debugging
    $error_log = implode("\n", $output);
    error_log("RAG Query Error: " . $error_log);
    
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar la consulta con el sistema RAG',
        'debug' => $error_log // Quitar en producción
    ]);
}
?>