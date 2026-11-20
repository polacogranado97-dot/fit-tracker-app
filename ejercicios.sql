-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 20-11-2026 a las 19:44:25
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `fit_tracker_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ejercicios`
--

CREATE TABLE `ejercicios` (
  `id` int(11) NOT NULL,
  `nombre_ejercicio` varchar(255) NOT NULL,
  `grupo_muscular` varchar(100) NOT NULL,
  `imagen_url` varchar(255) DEFAULT 'assets/ejercicios/default.gif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ejercicios`
--

INSERT INTO `ejercicios` (`id`, `nombre_ejercicio`, `grupo_muscular`, `imagen_url`, `created_at`) VALUES
(1, 'Press de banca con barra', 'Pecho', 'assets/ejercicios/press_banca_plano.svg', '2026-10-16 00:43:39'),
(2, 'Press inclinado con mancuernas', 'Pecho', 'assets/ejercicios/press_inclinado_mancuernas.svg', '2026-10-16 00:43:39'),
(3, 'Press declinado con barra', 'Pecho', 'assets/ejercicios/press_declinado_barra.svg', '2026-10-16 00:43:39'),
(4, 'Aperturas en banco plano', 'Pecho', 'assets/ejercicios/aperturas_banco_plano.svg', '2026-10-16 00:43:39'),
(5, 'Aperturas inclinadas con mancuernas', 'Pecho', 'assets/ejercicios/aperturas_inclinadas.svg', '2026-10-16 00:43:39'),
(6, 'Cruce de poleas desde abajo', 'Pecho', 'assets/ejercicios/cruce_poleas_abajo.svg', '2026-10-16 00:43:39'),
(7, 'Cruce de poleas desde arriba', 'Pecho', 'assets/ejercicios/cruce_poleas_arriba.svg', '2026-10-16 00:43:39'),
(8, 'Pec Deck / Contractor de pecho', 'Pecho', 'assets/ejercicios/pec_deck.svg', '2026-10-16 00:43:39'),
(9, 'Flexiones de pecho tradicionales', 'Pecho', 'assets/ejercicios/flexiones_pecho.svg', '2026-10-16 00:43:39'),
(10, 'Pullover con mancuerna', 'Pecho', 'assets/ejercicios/pullover_mancuerna.svg', '2026-10-16 00:43:39'),
(11, 'Remo con barra prono', 'Espalda', 'assets/ejercicios/remo_barra_prono.svg', '2026-10-16 00:43:39'),
(12, 'Jalon al pecho agarre ancho', 'Espalda', 'assets/ejercicios/jalon_al_pecho.svg', '2026-10-16 00:43:39'),
(13, 'Jalon al pecho agarre neutro', 'Espalda', 'assets/ejercicios/jalon_agarre_neutro.svg', '2026-10-16 00:43:39'),
(14, 'Dominadas en barra fija', 'Espalda', 'assets/ejercicios/dominadas.svg', '2026-10-16 00:43:39'),
(15, 'Remo en polea baja sentado', 'Espalda', 'assets/ejercicios/remo_polea_baja.svg', '2026-10-16 00:43:39'),
(16, 'Remo unipersonal con mancuerna', 'Espalda', 'assets/ejercicios/remo_mancuerna.svg', '2026-10-16 00:43:39'),
(17, 'Remo en barra T', 'Espalda', 'assets/ejercicios/remo_barra_t.svg', '2026-10-16 00:43:39'),
(18, 'Pull-over en polea alta con cuerda', 'Espalda', 'assets/ejercicios/pullover_polea.svg', '2026-10-16 00:43:39'),
(19, 'Encogimientos de hombros con mancuernas', 'Espalda', 'assets/ejercicios/encogimientos_trapecio.svg', '2026-10-16 00:43:39'),
(20, 'Hiperextensiones lumbares', 'Espalda', 'assets/ejercicios/hiperextensiones_lumbares.svg', '2026-10-16 00:43:39'),
(21, 'Press militar con mancuernas', 'Hombros', 'assets/ejercicios/press_militar_mancuernas.svg', '2026-10-16 00:43:39'),
(22, 'Press militar con barra de pie', 'Hombros', 'assets/ejercicios/press_militar_barra.svg', '2026-10-16 00:43:39'),
(23, 'Vuelos laterales con mancuernas', 'Hombros', 'assets/ejercicios/vuelos_laterales.svg', '2026-10-16 00:43:39'),
(24, 'Vuelos laterales en polea baja', 'Hombros', 'assets/ejercicios/vuelos_laterales_polea.svg', '2026-10-16 00:43:39'),
(25, 'Pajaros / Vuelos posteriores con mancuernas', 'Hombros', 'assets/ejercicios/vuelos_posteriores.svg', '2026-10-16 00:43:39'),
(26, 'Face pulls en polea con cuerda', 'Hombros', 'assets/ejercicios/face_pulls.svg', '2026-10-16 00:43:39'),
(27, 'Elevaciones frontales con disco', 'Hombros', 'assets/ejercicios/elevaciones_frontales_disco.svg', '2026-10-16 00:43:39'),
(28, 'Press Arnold con mancuernas', 'Hombros', 'assets/ejercicios/press_arnold.svg', '2026-10-16 00:43:39'),
(29, 'Sentadilla trasera con barra', 'Piernas', 'assets/ejercicios/sentadilla_con_barra.svg', '2026-10-16 00:43:39'),
(30, 'Sentadilla frontal con barra', 'Piernas', 'assets/ejercicios/sentadilla_frontal.svg', '2026-10-16 00:43:39'),
(31, 'Prensa de piernas 45 grados', 'Piernas', 'assets/ejercicios/prensa_piernas.svg', '2026-10-16 00:43:39'),
(32, 'Sillon cuadriceps / Extensiones', 'Piernas', 'assets/ejercicios/extensiones_cuadriceps.svg', '2026-10-16 00:43:39'),
(33, 'Sentadilla Hack en maquina', 'Piernas', 'assets/ejercicios/sentadilla_hack.svg', '2026-10-16 00:43:39'),
(34, 'Zancadas / Estocadas con mancuernas', 'Piernas', 'assets/ejercicios/zancadas_mancuernas.svg', '2026-10-16 00:43:39'),
(35, 'Sentadilla Búlgara con mancuernas', 'Piernas', 'assets/ejercicios/sentadilla_bulgara.svg', '2026-10-16 00:43:39'),
(36, 'Adductores en maquina', 'Piernas', 'assets/ejercicios/adductores_maquina.svg', '2026-10-16 00:43:39'),
(37, 'Peso muerto rumano', 'Isquios', 'assets/ejercicios/peso_muerto_rumano.svg', '2026-10-16 00:43:39'),
(38, 'Peso muerto convencional con barra', 'Isquios', 'assets/ejercicios/peso_muerto_convencional.svg', '2026-10-16 00:43:39'),
(39, 'Curl femoral acostado en maquina', 'Isquios', 'assets/ejercicios/curl_femoral_acostado.svg', '2026-10-16 00:43:39'),
(40, 'Curl femoral sentado en maquina', 'Isquios', 'assets/ejercicios/curl_femoral_sentado.svg', '2026-10-16 00:43:39'),
(41, 'Hip Thrust con barra', 'Gluteos', 'assets/ejercicios/hip_thrust.svg', '2026-10-16 00:43:39'),
(42, 'Patada de gluteo en polea', 'Gluteos', 'assets/ejercicios/patada_gluteo_polea.svg', '2026-10-16 00:43:39'),
(43, 'Elevacion de talones parado en maquina', 'Gemelos', 'assets/ejercicios/gemelos_parado.svg', '2026-10-16 00:43:39'),
(44, 'Elevacion de talones sentado', 'Gemelos', 'assets/ejercicios/gemelos_sentado.svg', '2026-10-16 00:43:39'),
(45, 'Curl de biceps con barra Z', 'Biceps', 'assets/ejercicios/curl_biceps_barra.svg', '2026-10-16 00:43:39'),
(46, 'Curl martillo con mancuernas', 'Biceps', 'assets/ejercicios/curl_martillo.svg', '2026-10-16 00:43:39'),
(47, 'Curl de biceps alternado con mancuernas', 'Biceps', 'assets/ejercicios/curl_alternado.svg', '2026-10-16 00:43:39'),
(48, 'Curl en banco Scott con barra Z', 'Biceps', 'assets/ejercicios/curl_scott.svg', '2026-10-16 00:43:39'),
(49, 'Curl concentrado con mancuerna', 'Biceps', 'assets/ejercicios/curl_concentrado.svg', '2026-10-16 00:43:39'),
(50, 'Curl en polea baja con barra recta', 'Biceps', 'assets/ejercicios/curl_polea_baja.svg', '2026-10-16 00:43:39'),
(51, 'Extension de triceps en polea con cuerda', 'Triceps', 'assets/ejercicios/extension_triceps_polea.svg', '2026-10-16 00:43:39'),
(52, 'Press frances con barra Z en banco plano', 'Triceps', 'assets/ejercicios/press_frances.svg', '2026-10-16 00:43:39'),
(53, 'Fondos en paralelas para triceps', 'Triceps', 'assets/ejercicios/fondos_paralelas.svg', '2026-10-16 00:43:39'),
(54, 'Extension copa a dos manos con mancuerna', 'Triceps', 'assets/ejercicios/extension_copa.svg', '2026-10-16 00:43:39'),
(55, 'Patada de triceps con mancuerna', 'Triceps', 'assets/ejercicios/patada_triceps.svg', '2026-10-16 00:43:39'),
(56, 'Crunches abdominales en colchoneta', 'Abdomen', 'assets/ejercicios/crunches_abdominales.svg', '2026-10-16 00:43:39'),
(57, 'Elevacion de piernas colgado en barra', 'Abdomen', 'assets/ejercicios/elevacion_piernas.svg', '2026-10-16 00:43:39'),
(58, 'Plancha abdominal isometrica', 'Abdomen', 'assets/ejercicios/plancha_isometrica.svg', '2026-10-16 00:43:39'),
(59, 'Rueda abdominal / Ab Wheel', 'Abdomen', 'assets/ejercicios/rueda_abdominal.svg', '2026-10-16 00:43:39'),
(60, 'Twist ruso con disco', 'Abdomen', 'assets/ejercicios/twist_ruso.svg', '2026-10-16 00:43:39');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `ejercicios`
--
ALTER TABLE `ejercicios`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `ejercicios`
--
ALTER TABLE `ejercicios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
