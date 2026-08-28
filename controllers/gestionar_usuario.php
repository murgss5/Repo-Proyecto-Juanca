<?php
session_start();
require_once __DIR__ . '/../config/conexion.php';

function volver(string $tipo, string $mensaje, int $rol, ?int $id = null): void
{
    $modulo = $rol === 3 ? 'aprendices' : 'instructores';
    $url = '../views/admin/' . $modulo . '/index.php?' . $tipo . '=' . urlencode($mensaje);
    if ($id !== null) $url .= '&id=' . $id;
    header('Location: ' . $url); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['fk_rol'] ?? null) != 1) { header('Location: ../views/auth/login.php'); exit; }
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) { exit('Solicitud no válida.'); }

$accion=$_POST['accion']??''; $rol=(int)($_POST['rol']??0); $id=filter_input(INPUT_POST,'id_usuario',FILTER_VALIDATE_INT);
if (!in_array($rol,[2,3],true) || !in_array($accion,['crear','editar','eliminar'],true)) exit('Datos no válidos.');
$conexion=(new Conexion())->conectar();
try {
 if ($accion==='eliminar') {
  if (!$id) volver('error','Usuario no válido.',$rol);
  $q=$conexion->prepare('SELECT id_usuario FROM usuario WHERE id_usuario=? AND fk_rol=?'); $q->execute([$id,$rol]); if(!$q->fetch()) volver('error','Usuario no encontrado.',$rol);
  if($rol===3){$q=$conexion->prepare('SELECT COUNT(*) FROM asistencia a INNER JOIN aprendiz ap ON a.fk_aprendiz=ap.id_aprendiz WHERE ap.fk_usuario=?');$q->execute([$id]); if($q->fetchColumn()) volver('error','No se puede eliminar un aprendiz con asistencias registradas.',$rol); $conexion->prepare('DELETE FROM aprendiz WHERE fk_usuario=?')->execute([$id]);}
  else {$q=$conexion->prepare('SELECT COUNT(*) FROM ficha WHERE fk_usuario=?');$q->execute([$id]); if($q->fetchColumn()) volver('error','No se puede eliminar un instructor con fichas asignadas.',$rol);}
  $conexion->prepare('DELETE FROM usuario WHERE id_usuario=?')->execute([$id]); volver('mensaje','Usuario eliminado correctamente.',$rol);
 }
 $usuario=trim($_POST['nombre_usuario']??'');$documento=trim($_POST['documento']??'');$nombre=trim($_POST['nombre']??'');$apellido=trim($_POST['apellido']??'');$telefono=trim($_POST['telefono']??'');$clave=$_POST['contrasena']??'';
 if($usuario===''||$documento===''||$nombre===''||$apellido===''||($accion==='crear'&&$clave==='')) volver('error','Complete los campos obligatorios.',$rol,$id?:null);
 $q=$conexion->prepare('SELECT id_usuario FROM usuario WHERE (nombre_usuario=:u OR identificacion=:d) AND id_usuario<>:id');$q->execute([':u'=>$usuario,':d'=>$documento,':id'=>$id?:0]);if($q->fetch())volver('error','El usuario o documento ya está registrado.',$rol,$id?:null);
 if($rol===3){$ficha=filter_input(INPUT_POST,'id_ficha',FILTER_VALIDATE_INT);$rfid=trim($_POST['codigo_rfid']??'');if(!$ficha||$rfid==='')volver('error','Ficha y RFID son obligatorios.',$rol,$id?:null);$q=$conexion->prepare('SELECT id_ficha FROM ficha WHERE id_ficha=?');$q->execute([$ficha]);if(!$q->fetch())volver('error','La ficha seleccionada no existe.',$rol,$id?:null);$q=$conexion->prepare('SELECT id_aprendiz FROM aprendiz WHERE codigo_rfid=? AND fk_usuario<>?');$q->execute([$rfid,$id?:0]);if($q->fetch())volver('error','El código RFID ya está registrado.',$rol,$id?:null);}
 $conexion->beginTransaction();
 if($accion==='crear'){$q=$conexion->prepare('INSERT INTO usuario(nombre_usuario,contrasena,nombre,apellido,identificacion,telefono,fk_rol) VALUES(?,?,?,?,?,?,?)');$q->execute([$usuario,password_hash($clave,PASSWORD_DEFAULT),$nombre,$apellido,$documento,$telefono?:null,$rol]);$id=(int)$conexion->lastInsertId();if($rol===3)$conexion->prepare('INSERT INTO aprendiz(codigo_rfid,fk_ficha,fk_usuario) VALUES(?,?,?)')->execute([$rfid,$ficha,$id]);}
 else {if(!$id)throw new RuntimeException();$sql='UPDATE usuario SET nombre_usuario=?,nombre=?,apellido=?,identificacion=?,telefono=?'.($clave!==''?',contrasena=?':'').' WHERE id_usuario=? AND fk_rol=?';$val=[$usuario,$nombre,$apellido,$documento,$telefono?:null];if($clave!=='')$val[]=password_hash($clave,PASSWORD_DEFAULT);$val[]= $id;$val[]=$rol;$q=$conexion->prepare($sql);$q->execute($val);if($rol===3)$conexion->prepare('UPDATE aprendiz SET codigo_rfid=?,fk_ficha=? WHERE fk_usuario=?')->execute([$rfid,$ficha,$id]);}
 $conexion->commit(); volver('mensaje',$accion==='crear'?'Usuario creado correctamente.':'Usuario actualizado correctamente.',$rol);
} catch(Throwable $e){if($conexion->inTransaction())$conexion->rollBack();volver('error','No fue posible guardar los datos.',$rol,$id?:null);}
