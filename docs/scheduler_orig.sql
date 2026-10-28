-- phpMyAdmin SQL Dump
-- version 4.7.0
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Мар 03 2018 г., 14:52
-- Версия сервера: 10.1.21-MariaDB
-- Версия PHP: 5.6.30
--
-- Структура адаптирована для MySQL 8.4 и текущего приложения.
-- Импортировать в пустую базу, затем выполнить php bin/migrate.php.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `scheduler`
--

-- --------------------------------------------------------

--
-- Структура таблицы `subject`
--

CREATE TABLE IF NOT EXISTS `subject` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `subject`
--

INSERT INTO `subject` (`id`, `name`) VALUES
(162, 'Valodas'),
(163, 'Humanitārās zinātnes'),
(164, 'Dabaszinatnes'),
(165, 'Fizikas,matemātikas un datorzinības zinatnes'),
(166, 'Sports'),
(167, 'Sākumskola');

-- --------------------------------------------------------

--
-- Структура таблицы `teacher`
--

CREATE TABLE IF NOT EXISTS `teacher` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `surname` varchar(255) NOT NULL DEFAULT '',
  `class` varchar(150) DEFAULT NULL,
  `cabinet` varchar(50) NOT NULL,
  `subject_id` int UNSIGNED NOT NULL,
  `available` tinyint(1) NOT NULL DEFAULT 1,
  `start_time` varchar(5) NOT NULL DEFAULT '17:00',
  `end_time` varchar(5) NOT NULL DEFAULT '19:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `teacher`
--

INSERT INTO `teacher` (`id`, `name`, `cabinet`, `subject_id`) VALUES
(222, 'Jeļena Serdjukova', '110', 167),
(223, 'Nataļja Mihailova', '109', 167),
(224, 'Nataļja Minajeva', '108', 167),
(225, 'Valentīna Sapogova', '103', 167),
(226, 'Ksenija Kirilova', '213', 167),
(227, 'Tamāra Meškova', '203', 167),
(228, 'Tatjana Aļeņina', '304', 167),
(229, 'Irina Panova', '310', 167),
(230, 'Jeļena Isjko', '206', 167),
(231, 'Ļubova Korņilova', '311', 167),
(232, 'Oxana Panashenko', '207', 167),
(233, 'Jeļena Ivaņenko', '309', 167),
(234, 'Valentīna Kruļikovska', '208', 167),
(235, 'Inna Jegorova', '46', 167),
(236, 'Gaļina Jerjomina', '29', 167),
(237, 'Jeļena Martjanova', '27', 167),
(238, 'Larisa Mickeviča', '212', 167),
(239, 'Natālija Jakovele', '206. a', 167),
(240, 'Inita Poļaka', '106. a', 167),
(241, 'Lorina Skurjate', '5. a', 167),
(242, 'Valentīna Lomakina', '79', 167),
(243, 'Alla Cavicka', '213. a', 167),
(244, 'Jevgēnija Urbane-Orbane', '304', 167),
(245, 'Evija Kijonoka', 'Skolotāju ist.', 167),
(246, 'Jekaterina Koršunova', '103', 167),
(247, 'Nataļja Piļipenko', '213', 167);

-- --------------------------------------------------------

--
-- Структура таблицы `counter`
--

CREATE TABLE IF NOT EXISTS `counter` (
  `id` int UNSIGNED NOT NULL PRIMARY KEY,
  `counter` bigint NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `counter`
--

INSERT INTO `counter` (`id`, `counter`) VALUES
(1, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `text`
--

CREATE TABLE IF NOT EXISTS `text` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `code` varchar(255) NOT NULL,
  `text` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `text`
--

-- INSERT INTO `text` (`id`, `code`, `text`) VALUES
-- (1, 'main_page_middle_text', 'Cienījamie vecāki! Lūdzam ievērot reglamentu: no 17.00 līdz 19.00.'),
-- (2, 'main_page_sub_text', '2018.gada 22.martā plkst. 17.00 - 19.00.'),
-- (3, 'parents_header', 'Lietošanas instrukcija'),
-- (4, 'Fizikas,matemātikas un datorzinības zinatnes'),
-- (166, 'Sports'),
-- (167, 'Sākumskola');

-- --------------------------------------------------------

--
-- Структура таблицы `vecaki`
--

CREATE TABLE IF NOT EXISTS `vecaki` (
  `id` int NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `grade` varchar(255) NOT NULL,
  `parent_name` varchar(255) NOT NULL,
  `contact_phone` varchar(255) NOT NULL,
  `time` varchar(5) NOT NULL,
  `is_avaliable` tinyint(1) DEFAULT NULL,
  `teacher_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `subject`
--
ALTER TABLE `subject`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `teacher`
--
ALTER TABLE `teacher`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_teacher_subject1_idx` (`subject_id`);

--
-- Индексы таблицы `vecaki`
--
ALTER TABLE `vecaki`
  ADD PRIMARY KEY (`id`),
  ADD CONSTRAINT `unique_teacher_time` UNIQUE (`teacher_id`, `time`),
  ADD KEY `fk_vecaki_teacher1_idx` (`teacher_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `subject`
--
ALTER TABLE `subject`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;
--
-- AUTO_INCREMENT для таблицы `teacher`
--
ALTER TABLE `teacher`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=248;
--
-- AUTO_INCREMENT для таблицы `vecaki`
--
ALTER TABLE `vecaki`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `teacher`
--
ALTER TABLE `teacher`
  ADD CONSTRAINT `fk_teacher_subject1` FOREIGN KEY (`subject_id`) REFERENCES `subject` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Ограничения внешнего ключа таблицы `vecaki`
--
ALTER TABLE `vecaki`
  ADD CONSTRAINT `fk_vecaki_teacher1` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
