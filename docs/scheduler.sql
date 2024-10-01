-- phpMyAdmin SQL Dump
-- version 4.7.0
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Мар 03 2018 г., 14:52
-- Версия сервера: 10.1.21-MariaDB
-- Версия PHP: 5.6.30

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
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `cabinet` varchar(50) DEFAULT NULL,
  `subject_id` int(11) UNSIGNED NOT NULL,
  `available` bool DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
  `id` int(11) UNSIGNED NOT NULL,
  `counter` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `counter`
--

INSERT INTO `counter` (`id`, `counter`) VALUES
(1, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `texts`
--

CREATE TABLE IF NOT EXISTS `texts` (
  `id` int(11) UNSIGNED NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `text` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `texts`
--

INSERT INTO `text` (`id`, `code`, `text`) VALUES
(1, 'main_page_middle_text', 'Cienījamie vecāki! Lūdzam ievērot reglamentu: no 17.00 līdz 19.00.'),
(2, 'main_page_sub_text', '2018.gada 22.martā plkst. 17.00 - 19.00.'),
(3, 'parents_header', 'Lietošanas instrukcija'),
(4, 'Fizikas,matemātikas un datorzinības zinatnes'),
(166, 'Sports'),
(167, 'Sākumskola');

-- --------------------------------------------------------

--
-- Структура таблицы `vecaki`
--

CREATE TABLE IF NOT EXISTS `vecaki` (
  `id` int(11) NOT NULL,
  `student_name` varchar(255) DEFAULT NULL,
  `grade` varchar(255) DEFAULT NULL,
  `parent_name` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(255) DEFAULT NULL,
  `time` varchar(20) DEFAULT NULL,
  `is_avaliable` tinyint(1) DEFAULT NULL,
  `teacher_id` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
  ADD KEY `fk_vecaki_teacher1_idx` (`teacher_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `subject`
--
ALTER TABLE `subject`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;
--
-- AUTO_INCREMENT для таблицы `teacher`
--
ALTER TABLE `teacher`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=248;
--
-- AUTO_INCREMENT для таблицы `vecaki`
--
ALTER TABLE `vecaki`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
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
