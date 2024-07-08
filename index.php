<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once 'database.php';

$db = new DataBase();
$con = $db->conectar();

$dominiosPermitidos = [
    "https://localhost:5173"
];

// if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $dominiosPermitidos)) {
//     header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
// }

header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, DELETE");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: token, Content-Type');
    header('Access-Control-Max-Age: 1728000');
    header('Content-Length: 0');
    header('Content-Type: text/plain');
    die();
    }

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function enviarRespuesta($data) {
    global $dominiosPermitidos;
    if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $dominiosPermitidos)) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}


if (isset($_GET['action'])) {
    $action = $_GET['action'];
    switch ($action) {
        case 'productos':
            $sql = $con->prepare("SELECT * FROM productos");
            $sql->execute();
            $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
            enviarRespuesta($resultado);
            break;

        case 'montominimo':
            $sql2 = $con->prepare("SELECT * FROM montominimo");
            $sql2->execute();
            $montos = $sql2->fetchAll(PDO::FETCH_ASSOC);
            enviarRespuesta($montos);
            break;

        case 'montos':
            $sql = $con->prepare("SELECT * FROM montos");
            $sql->execute();
            $montos = $sql->fetchAll(PDO::FETCH_ASSOC);
            enviarRespuesta($montos);
            break;

        case 'login':
            $sql3 = $con->prepare("SELECT * FROM logcred");
            $sql3->execute();
            $credenciales = $sql3->fetchAll(PDO::FETCH_ASSOC);
            enviarRespuesta($credenciales);
            break;

        case 'contact':
            $sql4 = $con->prepare("SELECT * FROM contacto");
            $sql4->execute();
            $contacto = $sql4->fetchAll(PDO::FETCH_ASSOC);
            enviarRespuesta($contacto);
            break;

        case 'shipments':
            $sql5 = $con->prepare("SELECT * FROM entregas");
            $sql5->execute();
            $entregas = $sql5->fetchAll(PDO::FETCH_ASSOC);
            enviarRespuesta($entregas);
            break;

        case 'orders':
            $sql6 = $con->prepare("SELECT * FROM pedidos");
            $sql6->execute();
            $pedidos = $sql6->fetchAll(PDO::FETCH_ASSOC);
            enviarRespuesta($pedidos);
            break;

        case 'update-seen-status':

            $data = json_decode(file_get_contents('php://input'), true);
        
            if (isset($data['id_pedido']) && isset($data['seen'])) {
                $orderId = intval($data['id_pedido']);
                $seenStatus = intval($data['seen']); // Convertir a entero ya que es un tinyint(1)
            
                $sql = "UPDATE pedidos SET visto ='$seenStatus' WHERE id_pedido = '$orderId'";
            
                if ($con->query($sql)) {
                    enviarRespuesta(['success' => true]);
                } else {
                    enviarRespuesta(['success' => false, 'error']);
                }
            } else {
                enviarRespuesta(['status' => 'error', 'message' => 'Datos incompletos']);
            }
            break;

        case 'save-order':
            $data = json_decode(file_get_contents("php://input"), true);

            if (isset($data['nombre_cliente'], $data['email_cliente'], $data['telefono_cliente'], $data['direccion_cliente'], $data['fecha_pedido'], $data['detalle'], $data['total_pedido'], $data['visto'], $data['tipo_comprador'], $data['email'], $data['telefono'])) {
                $nombre_cliente = $data['nombre_cliente'];
                $email_cliente = $data['email_cliente'];
                $telefono_cliente = $data['telefono_cliente'];
                $direccion_cliente = $data['direccion_cliente'];
                $fecha_pedido = $data['fecha_pedido'];
                $detalle = $data['detalle'];
                $total_pedido = floatval($data['total_pedido']);
                $visto = $data['visto'];
                $comprador = $data['tipo_comprador'];
                $email = $data['email'];
                $telefono = $data['telefono'];

                $sql = $con->prepare("INSERT INTO pedidos (nombre_cliente, email_cliente, telefono_cliente, direccion_cliente, fecha_pedido, detalle, total, visto, tipo_comprador) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $sql->execute([$nombre_cliente, $email_cliente, $telefono_cliente, $direccion_cliente, $fecha_pedido, $detalle, $total_pedido, $visto, $comprador]);

                if ($sql->rowCount() > 0) {
                    try {
                        $mail = new PHPMailer(true);

                        // Configuración del servidor
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'pedidodarrona@gmail.com';
                        $mail->Password = 'kjqpdlybcfuvglhk';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        $mail->setFrom('pedidodarrona@gmail.com', 'Diatribuidora Darrona');
                        $mail->addAddress('nataliagiselle.c@gmail.com');

                        // Contenido
                        $mail->isHTML(true);
                        $mail->Subject = 'Nuevo pedido de compra';
                        $detalleArray = json_decode($detalle, true);
                        $detalleHTML = '';
                        foreach ($detalleArray as $item) {
                            $product = $item['product'];
                            $detalleHTML .= '<li>' . htmlspecialchars($product['Producto']) . ' - Cantidad: ' . htmlspecialchars($item['quantity']) . ' - Total: $' . number_format($item['totalProduct'], 2, ',', '.') . '</li>';
                        }
                        $mail->Body = '
                            <html>
                            <body>
                                <h2>Nuevo pedido de compra</h2>
                                <p>Hola Darrona,</p>
                                <p>Acabas de recibir un nuevo pedido de compra de:</p>
                                <ul>
                                    <li><strong>Nombre:</strong> ' . htmlspecialchars($nombre_cliente) . '</li>
                                    <li><strong>Email:</strong> ' . htmlspecialchars($email_cliente) . '</li>
                                    <li><strong>Teléfono:</strong> ' . htmlspecialchars($telefono_cliente) . '</li>
                                    <li><strong>Dirección:</strong> ' . htmlspecialchars($direccion_cliente) . '</li>
                                    <li><strong>Fecha del pedido:</strong> ' . htmlspecialchars($fecha_pedido) . '</li>
                                    <li><strong>Total del pedido:</strong> $' . number_format($total_pedido, 2, ',', '.') . '</li>
                                </ul>
                                <h3>Detalle del pedido:</h3>
                                <ul>' . $detalleHTML . '</ul>
                                <p>Saludos,<br>Tu tienda en línea</p>
                            </body>
                            </html>';
                        $mail->AltBody = 'Hola Darrona,\n\nAcabas de recibir un nuevo pedido de compra de: ' . htmlspecialchars($nombre_cliente) . '.\n\nSaludos,\nTu tienda en línea';

                        $mail->send();

                        $mailCliente = new PHPMailer(true);
                        $mailCliente->isSMTP();
                        $mailCliente->Host = 'smtp.gmail.com';
                        $mailCliente->SMTPAuth = true;
                        $mailCliente->Username = 'pedidodarrona@gmail.com';
                        $mailCliente->Password = 'kjqpdlybcfuvglhk';
                        $mailCliente->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mailCliente->Port = 587;

                        $mailCliente->setFrom('pedidodarrona@gmail.com', 'Diatribuidora Darrona');
                        $mailCliente->addAddress($email_cliente);

                        $mailCliente->isHTML(true);
                        $mailCliente->Subject = 'Pedido Darrona';
                        $detalleArray = json_decode($detalle, true);
                        $detalleHTMLcliente = '';
                        foreach ($detalleArray as $item) {
                            $product = $item['product'];
                            $detalleHTMLcliente .= '<li>' . htmlspecialchars($product['Producto']) . ' - Cantidad: ' . htmlspecialchars($item['quantity']) . ' - Total: $' . number_format($item['totalProduct'], 2, ',', '.') . '</li>';
                        }

                        $mailCliente->Body = '
                            <html>
                            <body>
                                <h2>Confirmación de tu pedido</h2>
                                <p>Hola ' . htmlspecialchars($nombre_cliente) . ',</p>
                                <p>Hemos recibido tu pedido y estamos procesándolo. Pronto nos comunicaremos para concreatar el pago y la entrega.\n Aquí tienes los detalles de tu pedido:</p>
                                <ul>
                                    <li><strong>Fecha del pedido:</strong> ' . htmlspecialchars($fecha_pedido) . '</li>
                                    <li><strong>Total del pedido:</strong> $' . number_format($total_pedido, 2, ',', '.') . '</li>
                                </ul>
                                <h3>Detalle del pedido:</h3>
                                <ul>' . $detalleHTMLcliente . '</ul>
                                <p><strong>No responder este email</strong></p>
                                <p><strong>Para comunicarte con nosotros hacerlo via mensaje Whatsapp al ' . htmlspecialchars($telefono) . '.</strong></p>
                                <p>Gracias por elegirnos.<p>
                                <p>Saludos,<br>Distrinuidora Darrona</p>
                            </body>
                            </html>';
                        $mailCliente->AltBody = 'Hola ' . htmlspecialchars($nombre_cliente) . ',\n\nGracias por tu compra. Hemos recibido tu pedido y estamos procesándolo. Aquí tienes los detalles de tu pedido:\n\nNombre: ' . htmlspecialchars($nombre_cliente) . '\nEmail: ' . htmlspecialchars($email_cliente) . '\nTeléfono: ' . htmlspecialchars($telefono_cliente) . '\nDirección: ' . htmlspecialchars($direccion_cliente) . '\nFecha del pedido: ' . htmlspecialchars($fecha_pedido) . '\nTotal del pedido: $' . number_format($total_pedido, 2, ',', '.') . '\n\nSaludos,\nTu tienda en línea';

                        $mailCliente->send();

                        echo enviarRespuesta(['success' => 'Pedido guardado y correo enviado exitosamente']);
                    } catch (Exception $e) {
                        echo enviarRespuesta(['error' => "El correo no pudo ser enviado. Error de PHPMailer: {$mail->ErrorInfo}"]);
                    }
                } else {
                    echo enviarRespuesta(['error' => 'Error al guardar el pedido']);
                }
            } else {
                echo enviarRespuesta(['error' => 'Datos incompletos']);
            }
            break;

        case 'recover-cred':
            $data = json_decode(file_get_contents("php://input"), true);

            if (isset($data['us'], $data['pass'])) {
                $us = $data['us'];
                $pass = $data['pass'];

                try {
                    $mail = new PHPMailer(true);

                    // Configuración del servidor
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'pedidodarrona@gmail.com';
                    $mail->Password = 'kjqpdlybcfuvglhk';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('pedidodarrona@gmail.com', 'Diatribuidora Darrona');
                    $mail->addAddress('nataliagiselle.c@gmail.com');

                    // Contenido
                    $mail->isHTML(true);
                    $mail->Subject = 'Recuperacion de Usuario';
                    $mail->Body = '
                        <html>
                        <body>
                            <h2>Recuperación de usuario</h2>
                            <p>Hola Darrona,</p>
                            <p>Estas son tus credenciales:</p>
                            <ul>
                                <li><strong>Usuario:</strong> ' . htmlspecialchars($us) . '</li>
                                <li><strong>Clave:</strong> ' . htmlspecialchars($pass) . '</li>
                            </ul>
                            <p>Saludos,<br>Tu tienda en línea</p>
                        </body>
                        </html>';
                    $mail->AltBody = 'Hola Darrona,\n\nEstas son tus credenciales: Usuario:' . htmlspecialchars($us) .'clave:' . htmlspecialchars($us) . '.\n\nSaludos,\nTu tienda en línea';

                    $mail->send();
                } catch (Exception $e) {
                    echo enviarRespuesta(['error' => "El correo no pudo ser enviado. Error de PHPMailer: {$mail->ErrorInfo}"]);
                }
            }
            break;

        default:
            echo enviarRespuesta(['error' => 'acción no válida']);
            break;
    }
} else {
    echo enviarRespuesta(['error' => 'acción no válida']);
}
