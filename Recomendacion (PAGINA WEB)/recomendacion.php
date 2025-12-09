<?php

include '../../../database/config.php';
session_start();
if (!isset($_SESSION['correoEstudiante'])) {
    header('location: ../../../index.html');
    exit();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$correoEstudiante = $_SESSION['correoEstudiante'];

if (!$conn) {
    die("Error de conexión: " . print_r(sqlsrv_errors(), true));
}

$regenerar = isset($_POST['regenerar']) && $_POST['regenerar'] === 'true';

$recommendation = null;
$existeRecomendacion = false;

if (!$regenerar) {
    $queryVerificar = "SELECT TOP 1 resultadoID, carrera1, carrera2, carrera3, 
                       explicacion1, explicacion2, explicacion3, area
                       FROM resultado_orientacion 
                       WHERE correo = ?
                       ORDER BY resultadoID DESC";
    
    $paramsVerificar = array($correoEstudiante);
    $stmtVerificar = sqlsrv_query($conn, $queryVerificar, $paramsVerificar);
    
    if ($stmtVerificar && $row = sqlsrv_fetch_array($stmtVerificar, SQLSRV_FETCH_ASSOC)) {
        $existeRecomendacion = true;
        $recommendation = [
            'Area_Principal' => $row['area'],
            'Profesiones_sugeridas' => [
                [
                    'profesion' => $row['carrera1'],
                    'descripcion' => $row['explicacion1']
                ],
                [
                    'profesion' => $row['carrera2'],
                    'descripcion' => $row['explicacion2']
                ],
                [
                    'profesion' => $row['carrera3'],
                    'descripcion' => $row['explicacion3']
                ]
            ]
        ];
    }
    sqlsrv_free_stmt($stmtVerificar);
}

if (!$existeRecomendacion || $regenerar) {

    $queryAcademicos = "SELECT c.materia AS Materia, AVG(a.nota_final) AS promedio
                        FROM asignado a
                        JOIN classroom c ON a.classID = c.classID
                        WHERE a.correo = ?
                        GROUP BY c.materia";

    $paramsAcademicos = array($correoEstudiante);
    $stmtAcademicos = sqlsrv_query($conn, $queryAcademicos, $paramsAcademicos);
    if (!$stmtAcademicos) {
        die("Error al obtener datos académicos: " . print_r(sqlsrv_errors(), true));
    }

    $academicos = [
        'matematica' => 0,
        'lenguaje' => 0,
        'sociales' => 0,
        'ciencias' => 0
    ];

    while ($row = sqlsrv_fetch_array($stmtAcademicos, SQLSRV_FETCH_ASSOC)) {
        switch (strtolower($row['Materia'])) {
            case "matematica":
                $academicos['matematica'] = $row['promedio'];
                break;
            case "lenguaje":
                $academicos['lenguaje'] = $row['promedio'];
                break;
            case "sociales":
                $academicos['sociales'] = $row['promedio'];
                break;
            case "ciencias_naturales":
                $academicos['ciencias'] = $row['promedio'];
                break;
            case "fisica":
                $academicos['ciencias'] = (($academicos['ciencias'] * 3) + $row['promedio'])/4;
                break;
            case "quimica":
                $academicos['ciencias'] = (($academicos['ciencias'] * 4) + $row['promedio'])/5;
                break;
        }
    }
    sqlsrv_free_stmt($stmtAcademicos);

    $queryJuegos = "
        SELECT g.area, AVG(j.puntuacion) AS promedio_puntuacion
        FROM juega j
        JOIN gamificacion g ON j.gameID = g.gameID
        WHERE j.correo = ?
        GROUP BY g.area
    ";
    $paramsJuegos = array($correoEstudiante);
    $stmtJuegos = sqlsrv_query($conn, $queryJuegos, $paramsJuegos);
    if (!$stmtJuegos) {
        die("Error al obtener datos de juegos: " . print_r(sqlsrv_errors(), true));
    }

    $juegos = [
        'matematica' => 0,
        'lenguaje' => 0,
        'historia' => 0,
        'quimica' => 0
    ];
    
    while ($row = sqlsrv_fetch_array($stmtJuegos, SQLSRV_FETCH_ASSOC)) {
        $area = strtolower($row['area']);
        if (isset($juegos[$area])) {
            $juegos[$area] = round($row['promedio_puntuacion'], 2);
        }
    }

    sqlsrv_free_stmt($stmtJuegos);

    $data = [
        'datos_academicos' => $academicos,
        'datos_juegos' => $juegos
    ];

    $apiKey = 'KEY'; // Tu clave de API aquí
    $url = 'https://api.openai.com/v1/chat/completions';

    $postData = [
        'model' => 'gpt-4',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Eres un sistema de recomendación vocacional que analiza puntajes de notas y juegos. 
                IMPORTANTE: Solo puedes recomendar carreras usando nombres genéricos como "Ingeniería en Sistemas", "Licenciatura en Medicina", "Arquitectura", etc.
                Genera resultados en el siguiente formato JSON:
                {
                    "recomendacion_vocacional": {
                        "Area_Principal": "Nombre del área principal",
                        "Profesiones_sugeridas": [
                            {
                                "profesion": "Nombre Genérico de Carrera 1",
                                "descripcion": "Explicación breve de por qué se recomienda esta carrera basándose en las fortalezas del estudiante"
                            },
                            {
                                "profesion": "Nombre Genérico de Carrera 2",
                                "descripcion": "Explicación breve de por qué se recomienda esta carrera basándose en las fortalezas del estudiante"
                            },
                            {
                                "profesion": "Nombre Genérico de Carrera 3",
                                "descripcion": "Explicación breve de por qué se recomienda esta carrera basándose en las fortalezas del estudiante"
                            }
                        ]
                    }
                }'
            ],
            [
                'role' => 'user',
                'content' => "Con base en los siguientes datos:\n\nDatos académicos (promedios): " . json_encode($data['datos_academicos']) . "\n\nDatos de juegos (promedios de puntuación por área): " . json_encode($data['datos_juegos']) . "\n\nGenera una recomendación vocacional en el formato JSON descrito anteriormente. Usa solo nombres genéricos de carreras."
            ]
        ],
        'max_tokens' => 400
    ];

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        die("Error en la conexión al API: " . curl_error($ch));
    }
    curl_close($ch);

    $result = json_decode($response, true);


    if (isset($result['choices'][0]['message']['content'])) {
        $recommendationContent = $result['choices'][0]['message']['content'];
        $decodedRecommendation = json_decode($recommendationContent, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($decodedRecommendation['recomendacion_vocacional'])) {
            $recommendation = $decodedRecommendation['recomendacion_vocacional'];
            

            if ($regenerar) {
                $queryUpdate = "UPDATE resultado_orientacion 
                               SET carrera1 = ?, carrera2 = ?, carrera3 = ?,
                                   explicacion1 = ?, explicacion2 = ?, explicacion3 = ?,
                                   area = ?
                               WHERE correo = ?";
                
                $paramsUpdate = array(
                    $recommendation['Profesiones_sugeridas'][0]['profesion'],
                    $recommendation['Profesiones_sugeridas'][1]['profesion'],
                    $recommendation['Profesiones_sugeridas'][2]['profesion'],
                    $recommendation['Profesiones_sugeridas'][0]['descripcion'],
                    $recommendation['Profesiones_sugeridas'][1]['descripcion'],
                    $recommendation['Profesiones_sugeridas'][2]['descripcion'],
                    $recommendation['Area_Principal'],
                    $correoEstudiante
                );
                
                $stmtUpdate = sqlsrv_query($conn, $queryUpdate, $paramsUpdate);
                if (!$stmtUpdate) {
                    echo "<p>Error al actualizar en la base de datos: " . print_r(sqlsrv_errors(), true) . "</p>";
                }
                sqlsrv_free_stmt($stmtUpdate);
            } else {

                $queryInsert = "INSERT INTO resultado_orientacion 
                               (carrera1, carrera2, carrera3, explicacion1, explicacion2, explicacion3, area, correo)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $paramsInsert = array(
                    $recommendation['Profesiones_sugeridas'][0]['profesion'],
                    $recommendation['Profesiones_sugeridas'][1]['profesion'],
                    $recommendation['Profesiones_sugeridas'][2]['profesion'],
                    $recommendation['Profesiones_sugeridas'][0]['descripcion'],
                    $recommendation['Profesiones_sugeridas'][1]['descripcion'],
                    $recommendation['Profesiones_sugeridas'][2]['descripcion'],
                    $recommendation['Area_Principal'],
                    $correoEstudiante
                );
                
                $stmtInsert = sqlsrv_query($conn, $queryInsert, $paramsInsert);
                if (!$stmtInsert) {
                    echo "<p>Error al guardar en la base de datos: " . print_r(sqlsrv_errors(), true) . "</p>";
                }
                sqlsrv_free_stmt($stmtInsert);
            }
        } else {
            echo "<p>Error al decodificar la recomendación o estructura incorrecta: " . json_last_error_msg() . "</p>";
        }
    } else {
        echo "<p>Error: No se encontró contenido en la respuesta de la API.</p>";
    }
}

sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Charmie - Recomendación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@700&display=swap" rel="stylesheet">
    <link rel="icon" href="../../images/logo.ico" type="image/x-icon">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Josefin Sans', sans-serif;
        }

        .navbar {
            background: #ffffff !important;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            padding: 1rem 2rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3); /* Añade una sombra más pronunciada */
        }

        .navbar-brand img {
            transition: transform 0.3s ease;
        }

        .navbar-brand img:hover {
            transform: scale(1.05);
        }

        .nav-link {
            color: #333 !important;
            font-weight: 500;
            transition: color 0.3s ease;
            padding: 0.5rem 1rem !important;
        }

        .nav-link:hover {
            color: #53c453 !important;
        }

        .card {
            background: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
            border-radius: 10px;
        }

        .card-title {
            font-size: 1.5rem;
            color: #52796F;
        }

        ul {
            padding-left: 1.5rem;
        }

        ul li {
            font-size: 1.2rem;
            color: #2F3E46;
        }

        h1 {
            color: #354F52;
            font-weight: bold;
            margin-bottom: 2rem;
        }

        .btn-regenerar {
            background-color: #00B800;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .btn-regenerar:hover {
            background-color: #006400;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .tooltip-inner {
            background-color: #354F52;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 500;
            padding: 15px 20px;
            border-radius: 10px;
            text-align: center;
            max-width: 250px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .tooltip.bs-tooltip-end .tooltip-arrow::before {
            border-right-color: #354F52;
        }

        .tooltip.show {
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profesion-tooltip i {
            margin-right: 8px;
            font-size: 1.2rem;
            color: #52796F;
        }

        .loading-spinner {
            display: none;
        }

        .loading-spinner.active {
            display: inline-block;
        }

        .btn-consultas {
        background: linear-gradient(135deg, #00B800 0%, #00B800 100%);
        color: white;
        border: none;
        padding: 12px 35px;
        border-radius: 25px;
        font-size: 1.1rem;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(82, 121, 111, 0.3);
    }

    .btn-consultas:hover {
        background: linear-gradient(135deg, #006400 0%, #006400 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(82, 121, 111, 0.4);
    }

    .btn-consultas i {
        margin-right: 8px;
        font-size: 1.2rem;
    }

    .modal-content {
        border-radius: 15px;
        border: none;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        border-top-left-radius: 15px;
        border-top-right-radius: 15px;
        border-bottom: none;
    }

    .modal-body {
        padding: 2rem;
    }

    .alert-info {
        background-color: #e7f3ff;
        border-color: #52796F;
        color: #2F3E46;
    }

    .alert-success {
        background-color: #d4edda;
        border-color: #52796F;
    }

    .list-group-item {
        border-left: none;
        border-right: none;
        padding: 0.75rem 1rem;
    }

    .list-group-item:first-child {
        border-top: none;
    }

    .list-group-item:last-child {
        border-bottom: none;
    }
    </style>
</head>

<body>
<header>
<nav class="navbar navbar-expand-lg text-bg-light border-0">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">
      <img src="../../../images/logo.svg" alt="Logo" width="50" class="d-inline-block align-text-top">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAltMarkup">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
      <div class="navbar-nav">
        <a class="nav-link active" href="../Dashboardest.php" style="color: #333;">Dashboard</a>
      </div>
      <div class="navbar-nav ms-auto">
        <a class="nav-link active" href="../Logout/logout.php" style="color: #333;">
          <i class="bi bi-box-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>
</nav>
</header>

<div class="container d-flex justify-content-center mt-5">
    <div>
        <h1 class="text-center">Recomendación Vocacional</h1>
        <br>
        <?php if ($recommendation): ?>
            <div class="card p-4" style="max-width: 600px; width: 100%;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">Área Principal:</h5>
                        <p class="text-muted fs-5"><?php echo htmlspecialchars($recommendation['Area_Principal'] ?? 'No disponible'); ?></p>
                        <h5 class="mt-4">Carreras sugeridas:</h5>
                        <ul class="list-unstyled mt-3">
                        <?php if (!empty($recommendation['Profesiones_sugeridas'])): ?>
                            <?php foreach ($recommendation['Profesiones_sugeridas'] as $profesion): ?>
                                <li class="mb-2">
                                    <span class="profesion-tooltip" data-bs-toggle="tooltip" data-bs-placement="right" 
                                        title="<?php echo htmlspecialchars($profesion['descripcion']); ?>">
                                        <i class="bi bi-check-circle-fill text-success"></i> 
                                        <?php echo htmlspecialchars($profesion['profesion']); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No hay recomendaciones disponibles.</li>
                        <?php endif; ?>
                        </ul>
                    </div>
                    <img src="../../../images/cammain1.svg" alt="Recomendación" class="img-fluid"
                        style="max-width: 150px; border-radius: 10px;">
                </div>
                
                <!-- Formulario para regenerar -->
                <form method="POST" action="" id="formRegenerar" class="text-center">
                    <input type="hidden" name="regenerar" value="true">
                    <button type="submit" class="btn btn-regenerar" id="btnRegenerar">
                        <i class="bi bi-arrow-clockwise"></i> Regenerar Recomendación
                    </button>
                    <div class="spinner-border text-primary loading-spinner" role="status" id="loadingSpinner">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <p class="text-center">No se pudo generar una recomendación por el momento. Intenta de nuevo más tarde.</p>
        <?php endif; ?>
    

        <!-- Botón de Consultas RAG -->
        <?php if ($recommendation): ?>
            <div class="text-center mt-4">
                <button type="button" class="btn btn-consultas" data-bs-toggle="modal" data-bs-target="#modalPrivacidad">
                    <i class="bi bi-chat-dots"></i> Realizar Consultas sobre Carreras
                </button>
            </div>
        <?php endif; ?>

        <!-- Modal de Privacidad -->
        <div class="modal fade" id="modalPrivacidad" tabindex="-1" aria-labelledby="modalPrivacidadLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: #52796F; color: white;">
                        <h5 class="modal-title" id="modalPrivacidadLabel">
                            <i class="bi bi-shield-check"></i> Aviso de Privacidad
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info d-flex align-items-center" role="alert">
                            <i class="bi bi-info-circle-fill me-3" style="font-size: 2rem;"></i>
                            <div>
                                <h6 class="alert-heading mb-2">Sistema de Consultas Inteligente</h6>
                                <p class="mb-0">
                                    Para brindarte información más precisa y personalizada sobre las carreras recomendadas, 
                                    nuestro sistema de IA puede utilizar:
                                </p>
                            </div>
                        </div>
                        
                        <ul class="list-group list-group-flush mb-3">
                            <li class="list-group-item">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                Tus carreras recomendadas
                            </li>
                            <li class="list-group-item">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                Tu área vocacional principal
                            </li>
                            <li class="list-group-item">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                Información pública de universidades
                            </li>
                        </ul>

                        <div class="alert alert-success mb-0" role="alert">
                            <i class="bi bi-lock-fill me-2"></i>
                            <strong>Tu privacidad es importante:</strong> Solo se utilizará esta información para 
                            responder tus consultas y no será compartida con terceros.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <a href="consultas_rag.php" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Aceptar y Continuar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Inicializar tooltips de Bootstrap
    document.addEventListener("DOMContentLoaded", function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Manejar el formulario de regenerar
        document.getElementById('formRegenerar').addEventListener('submit', function() {
            document.getElementById('btnRegenerar').style.display = 'none';
            document.getElementById('loadingSpinner').classList.add('active');
        });
    });
</script>
</body>
</html>