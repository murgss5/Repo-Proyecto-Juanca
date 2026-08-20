<?php
/**
 * ============================================================
 * PUNTO DE ENTRADA DEL SISTEMA (PROTOTIPO VISUAL)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Como aún no existe autenticación funcional, este archivo
 * redirige a la vista de inicio de sesión.
 *
 * Cuando se desarrolle la lógica real, este archivo incluirá
 * el controlador de autenticación, la validación de sesiones
 * y el enrutamiento a los módulos por rol.
 * ============================================================
 */

header('Location: views/auth/login.php');
exit;