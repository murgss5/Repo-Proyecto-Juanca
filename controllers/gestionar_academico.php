<?php
session_start();
require_once __DIR__ . '/../config/conexion.php';

function volverAcademico(string $modulo, string $tipo, string $texto): void
{
    header('Location: ../views/admin/' . $modulo . '/index.php?' . $tipo . '=' . urlencode($texto));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['fk_rol'] ?? null) != 1) { header('Location: ../views/auth/login.php'); exit; }
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) exit('Solicitud no válida.');
$entidad = $_POST['entidad'] ?? ''; $accion = $_POST['accion'] ?? '';
$modulo = $entidad === 'ficha' ? 'fichas' : ($entidad === 'horario' ? 'horarios' : '');
if ($modulo === '' || !in_array($accion, ['crear','editar','eliminar'], true)) exit('Datos no válidos.');
$conexion = (new Conexion())->conectar();
try {
 if ($entidad === 'ficha') {
  $id=filter_input(INPUT_POST,'id_ficha',FILTER_VALIDATE_INT);
  if($accion==='eliminar'){if(!$id)volverAcademico($modulo,'error','Ficha no válida.');$q=$conexion->prepare('SELECT COUNT(*) FROM aprendiz WHERE fk_ficha=?');$q->execute([$id]);if($q->fetchColumn()>0)volverAcademico($modulo,'error','No se puede eliminar una ficha que tiene aprendices asignados.');$q=$conexion->prepare('DELETE FROM ficha WHERE id_ficha=?');$q->execute([$id]);if(!$q->rowCount())volverAcademico($modulo,'error','La ficha no existe.');volverAcademico($modulo,'mensaje','Ficha eliminada correctamente.');}
  $programa=trim($_POST['nombre_programa']??'');$jornada=trim($_POST['jornada']??'');$instructor=filter_input(INPUT_POST,'id_instructor',FILTER_VALIDATE_INT);
  if($programa===''||mb_strlen($programa)>100||$jornada===''||mb_strlen($jornada)>50||!$instructor)volverAcademico($modulo,'error','Complete los datos obligatorios de la ficha.');
  $q=$conexion->prepare('SELECT id_usuario FROM usuario WHERE id_usuario=? AND fk_rol=2');$q->execute([$instructor]);if(!$q->fetch())volverAcademico($modulo,'error','El instructor seleccionado no existe.');
  if($accion==='crear'){$conexion->prepare('INSERT INTO ficha(nombre_programa,jornada,fk_usuario) VALUES(?,?,?)')->execute([$programa,$jornada,$instructor]);volverAcademico($modulo,'mensaje','Ficha creada correctamente.');}
  if(!$id)volverAcademico($modulo,'error','Ficha no válida.');$conexion->prepare('UPDATE ficha SET nombre_programa=?,jornada=?,fk_usuario=? WHERE id_ficha=?')->execute([$programa,$jornada,$instructor,$id]);volverAcademico($modulo,'mensaje','Ficha actualizada correctamente.');
 }
 $id=filter_input(INPUT_POST,'id_horario',FILTER_VALIDATE_INT);
 if($accion==='eliminar'){if(!$id)volverAcademico($modulo,'error','Horario no válido.');$q=$conexion->prepare('DELETE FROM horario WHERE id_horario=?');$q->execute([$id]);if(!$q->rowCount())volverAcademico($modulo,'error','El horario no existe.');volverAcademico($modulo,'mensaje','Horario eliminado correctamente.');}
 $ficha=filter_input(INPUT_POST,'id_ficha',FILTER_VALIDATE_INT);$dia=trim($_POST['dia_semana']??'');$entrada=$_POST['entrada']??'';$salida=$_POST['salida']??'';$dias=['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
 if(!$ficha||!in_array($dia,$dias,true)||!preg_match('/^\d{2}:\d{2}$/',$entrada)||!preg_match('/^\d{2}:\d{2}$/',$salida)||$salida<=$entrada)volverAcademico($modulo,'error','Revise la ficha, el día y el intervalo de horas.');
 $q=$conexion->prepare('SELECT id_ficha FROM ficha WHERE id_ficha=?');$q->execute([$ficha]);if(!$q->fetch())volverAcademico($modulo,'error','La ficha seleccionada no existe.');$q=$conexion->prepare('SELECT id_horario FROM horario WHERE fk_ficha=? AND dia_semana=? AND id_horario<>?');$q->execute([$ficha,$dia,$id?:0]);if($q->fetch())volverAcademico($modulo,'error','Esta ficha ya tiene un horario para ese día.');
 if($accion==='crear'){$conexion->prepare('INSERT INTO horario(dia_semana,entrada,salida,fk_ficha) VALUES(?,?,?,?)')->execute([$dia,$entrada,$salida,$ficha]);volverAcademico($modulo,'mensaje','Horario creado correctamente.');}
 if(!$id)volverAcademico($modulo,'error','Horario no válido.');$conexion->prepare('UPDATE horario SET dia_semana=?,entrada=?,salida=?,fk_ficha=? WHERE id_horario=?')->execute([$dia,$entrada,$salida,$ficha,$id]);volverAcademico($modulo,'mensaje','Horario actualizado correctamente.');
} catch (Throwable $e) { volverAcademico($modulo,'error','No fue posible guardar los datos.'); }
