<?php
// Incluir la conexión a la base de datos
include '../../../database/config.php';
session_start();

if (!isset($_SESSION['correoEstudiante'])) {
    header('location: ../../../index.html');
    exit();
}

$correoEstudiante = $_SESSION['correoEstudiante'];

// Obtener recomendaciones del estudiante
$queryRecomendacion = "SELECT TOP 1 carrera1, carrera2, carrera3, area
                       FROM resultado_orientacion 
                       WHERE correo = ?
                       ORDER BY resultadoID DESC";

$paramsRec = array($correoEstudiante);
$stmtRec = sqlsrv_query($conn, $queryRecomendacion, $paramsRec);

$carreras = [];
$area = '';

if ($stmtRec && $row = sqlsrv_fetch_array($stmtRec, SQLSRV_FETCH_ASSOC)) {
    $carreras = [
        $row['carrera1'],
        $row['carrera2'],
        $row['carrera3']
    ];
    $area = $row['area'];
}

sqlsrv_free_stmt($stmtRec);
sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Charmie - Consultas Vocacionales</title>
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
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background: #ffffff !important;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            padding: 1rem 2rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
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

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            max-width: 1000px;
            margin: 2rem auto;
            width: 100%;
            padding: 0 1rem;
        }

        .chat-header {
            background: linear-gradient(135deg, #00B800 0%, #006400 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .chat-header h4 {
            margin: 0;
            font-weight: 600;
        }

        .carreras-info {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .carrera-badge {
            background-color: rgba(40, 40, 43, 1);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
            background: white;
            border-left: 1px solid #006400;
            border-right: 1px solid #006400;
            min-height: 400px;
            max-height: 500px;
        }

        .message {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 10px;
        }

        .message.user {
            justify-content: flex-end;
        }

        .message.assistant {
            justify-content: flex-start;
        }

        .message-content {
            max-width: 70%;
            padding: 1rem 1.5rem;
            border-radius: 15px;
            position: relative;
        }

        .message.user .message-content {
            background: linear-gradient(135deg, #00B800 0%, #006400 100%);
            color: white;
            border-bottom-right-radius: 5px;
        }

        .message.assistant .message-content {
            background: #00B800;
            color: #FFFFFF;
            border-bottom-left-radius: 5px;
        }

        .message-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .message.user .message-icon {
            background: ;
            color: white;
        }

        .message.assistant .message-icon {
            background: #00640000;
            color: white;
        }

        .chat-input-container {
            background: white;
            padding: 1.5rem;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.1);
        }

        .input-group {
            gap: 10px;
        }

        .chat-input {
            border: 2px solid #006400;
            border-radius: 25px;
            padding: 12px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .chat-input:focus {
            border-color: #52796F;
            box-shadow: 0 0 0 0.2rem rgba(82, 121, 111, 0.25);
        }

        .btn-send {
            background: linear-gradient(135deg, #006400 0%, #006400 100%);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-send:hover {
            background: linear-gradient(135deg, #006400 0%, #006400 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(82, 121, 111, 0.4);
        }

        .btn-send:disabled {
            background: #006400;
            cursor: not-allowed;
            transform: none;
        }

        .typing-indicator {
            display: none;
            padding: 1rem;
            gap: 5px;
        }

        .typing-indicator.active {
            display: flex;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            background-color: #006400;
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }

        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
            }
            30% {
                transform: translateY(-10px);
            }
        }

        .welcome-message {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }

        .welcome-message i {
            font-size: 4rem;
            color: #006400;
            margin-bottom: 1rem;
        }

        .suggested-questions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .suggested-question {
            background: white;
            border: 2px solid #006400;
            border-radius: 15px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
        }

        .suggested-question:hover {
            border-color: #006400;
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(82, 121, 111, 0.2);
        }

        .suggested-question i {
            color: #52796F;
            margin-right: 10px;
        }

        .error-message {
            background-color: #006400;
            border: 1px solid #006400;
            color: #00B800;
            padding: 1rem;
            border-radius: 10px;
            margin: 1rem 0;
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
        <a class="nav-link" href="../Dashboardest.php" style="color: #333;">Dashboard</a>
        <a class="nav-link active" href="recomendacion.php" style="color: #333;">Recomendación</a>
      </div>
      <div class="navbar-nav ms-auto">
        <a class="nav-link" href="../Logout/logout.php" style="color: #333;">
          <i class="bi bi-box-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>
</nav>
</header>

<div class="chat-container">
    <div class="chat-header">
        <h4></i> Asistente de Orientación Vocacional</h4>
        <?php if (!empty($carreras)): ?>
        <div class="carreras-info">
            <span><i class="bi bi-bookmark-fill"></i> Tus carreras:</span>
            <?php foreach ($carreras as $carrera): ?>
                <span class="carrera-badge"><?php echo htmlspecialchars($carrera); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="chat-messages" id="chatMessages">
        <div class="welcome-message" id="welcomeMessage">
            <i class="bi bi-chat-heart"></i>
            <h5>¡Hola! Estoy aquí para ayudarte</h5>
            <p>Pregúntame sobre las carreras recomendadas, pensums, materias y más.</p>
            
            <div class="suggested-questions">
                <div class="suggested-question" onclick="askQuestion('¿Qué materias tiene el pensum de <?php echo htmlspecialchars($carreras[0] ?? 'mi carrera'); ?>?')">
                    <i class="bi bi-book"></i>
                    <strong>Materias del pensum</strong>
                    <p class="text-muted mb-0 small">¿Qué materias veré?</p>
                </div>
                <div class="suggested-question" onclick="askQuestion('¿Cuántos créditos tiene la carrera?')">
                    <i class="bi bi-trophy"></i>
                    <strong>Créditos totales</strong>
                    <p class="text-muted mb-0 small">Duración de la carrera</p>
                </div>
                <div class="suggested-question" onclick="askQuestion('¿En qué ciclo se ve matemáticas?')">
                    <i class="bi bi-calendar-check"></i>
                    <strong>Organización</strong>
                    <p class="text-muted mb-0 small">¿Cuándo veo X materia?</p>
                </div>
                <div class="suggested-question" onclick="askQuestion('Compara estas carreras')">
                    <i class="bi bi-shuffle"></i>
                    <strong>Comparación</strong>
                    <p class="text-muted mb-0 small">Diferencias entre carreras</p>
                </div>
            </div>
        </div>

        <!-- Los mensajes se agregarán aquí dinámicamente -->
        
        <div class="message assistant typing-indicator" id="typingIndicator">
            <div class="message-icon">
                <img src="../../../images/logo.svg" alt="" width="40">
            </div>
            <div class="message-content">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            </div>
        </div>
    </div>

    <div class="chat-input-container">
        <div class="input-group">
            <input type="text" class="form-control chat-input" id="userInput" 
                   placeholder="Escribe tu pregunta aquí..." 
                   onkeypress="handleKeyPress(event)">
            <button class="btn btn-send" id="sendBtn" onclick="sendMessage()">
                <i class="bi bi-send-fill"></i> Enviar
            </button>
        </div>
    </div>
</div>

<script>
// Datos del estudiante
const carrerasRecomendadas = <?php echo json_encode($carreras); ?>;
const areaVocacional = <?php echo json_encode($area); ?>;

// Estado de la conversación
let isWaiting = false;

function handleKeyPress(event) {
    if (event.key === 'Enter' && !isWaiting) {
        sendMessage();
    }
}

function askQuestion(question) {
    document.getElementById('userInput').value = question;
    sendMessage();
}

async function sendMessage() {
    const input = document.getElementById('userInput');
    const message = input.value.trim();
    
    if (message === '' || isWaiting) return;
    
    // Ocultar mensaje de bienvenida
    document.getElementById('welcomeMessage').style.display = 'none';
    
    // Agregar mensaje del usuario
    addMessage(message, 'user');
    
    // Limpiar input
    input.value = '';
    
    // Deshabilitar input y botón
    isWaiting = true;
    document.getElementById('sendBtn').disabled = true;
    document.getElementById('userInput').disabled = true;
    
    // Mostrar indicador de escritura
    document.getElementById('typingIndicator').classList.add('active');
    
    try {
        // Llamar al API
        const response = await fetch('rag_query.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                query: message,
                carreras: carrerasRecomendadas,
                area: areaVocacional
            })
        });
        
        const data = await response.json();
        
        // Ocultar indicador de escritura
        document.getElementById('typingIndicator').classList.remove('active');
        
        if (data.success) {
            addMessage(data.respuesta, 'assistant', data.fuentes);
        } else {
            addMessage('Lo siento, hubo un error al procesar tu consulta: ' + data.error, 'assistant', null, true);
        }
        
    } catch (error) {
        document.getElementById('typingIndicator').classList.remove('active');
        addMessage('Lo siento, hubo un error de conexión. Por favor, intenta nuevamente.', 'assistant', null, true);
    } finally {
        // Habilitar input y botón
        isWaiting = false;
        document.getElementById('sendBtn').disabled = false;
        document.getElementById('userInput').disabled = false;
        document.getElementById('userInput').focus();
    }
}

function addMessage(text, type, fuentes = null, isError = false) {
    const messagesContainer = document.getElementById('chatMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    
    const icon = type === 'user' ? 
        '<i class="bi bi-person-circle"></i>' : 
        '<img src="../../../images/logo.svg" alt="" width="40">';
    
    let fuentesHTML = '';
    if (fuentes && fuentes.length > 0) {
        fuentesHTML = '<div class="mt-2 pt-2" style="border-top: 1px solid rgba(0,0,0,0.1); font-size: 0.85rem;">';
        fuentesHTML += '<i class="bi bi-book me-1"></i> <strong>Fuentes:</strong><br>';
        fuentes.forEach(fuente => {
            fuentesHTML += `<small>• ${fuente.carrera}</small><br>`;
        });
        fuentesHTML += '</div>';
    }
    
    messageDiv.innerHTML = `
        <div class="message-icon">${icon}</div>
        <div class="message-content ${isError ? 'error-message' : ''}">
            ${text}
            ${fuentesHTML}
        </div>
    `;
    
    messagesContainer.insertBefore(messageDiv, document.getElementById('typingIndicator'));
    
    // Scroll automático
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Enfocar el input al cargar
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('userInput').focus();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>