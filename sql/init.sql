-- phpMyAdmin SQL Dump
-- version 4.4.11
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jul 09, 2015 at 01:25 PM
-- Server version: 10.0.20-MariaDB-log
-- PHP Version: 5.6.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `throne2`
--

-- --------------------------------------------------------

--
-- Table structure for table `throne_alltime`
--

CREATE TABLE IF NOT EXISTS `throne_alltime` (
  `steamid` bigint(20) unsigned NOT NULL,
  `score` int(10) unsigned NOT NULL,
  `average` int(11) NOT NULL,
  `runs` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `throne_dates`
--

CREATE TABLE IF NOT EXISTS `throne_dates` (
  `dayId` int(11) NOT NULL,
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Table structure for table `throne_players`
--

CREATE TABLE IF NOT EXISTS `throne_players` (
  `steamid` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `avatar` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `twitch` varchar(255) NOT NULL,
  `suspected_hacker` tinyint(1) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `admin` tinyint(4) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- Table structure for table `throne_streams`
--

CREATE TABLE IF NOT EXISTS `throne_streams` (
  `name` varchar(255) COLLATE utf8_bin NOT NULL,
  `status` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `viewers` int(11) NOT NULL,
  `preview` varchar(255) COLLATE utf8_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


--
-- Table structure for table `throne_tokens`
--

CREATE TABLE IF NOT EXISTS `throne_tokens` (
  `token` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `last_accessed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `throne_alltime`
--
ALTER TABLE `throne_alltime`
  ADD PRIMARY KEY (`steamid`),
  ADD KEY `score` (`score`),
  ADD KEY `average` (`average`);

--
-- Indexes for table `throne_dates`
--
ALTER TABLE `throne_dates`
  ADD PRIMARY KEY (`dayId`);

--
-- Indexes for table `throne_players`
--
ALTER TABLE `throne_players`
  ADD PRIMARY KEY (`steamid`),
  ADD KEY `name` (`name`),
  ADD FULLTEXT KEY `name_2` (`name`);

--
-- Indexes for table `throne_scores`
--
ALTER TABLE `throne_scores`
  ADD PRIMARY KEY (`hash`),
  ADD KEY `dayId` (`dayId`),
  ADD KEY `score` (`score`);

--
-- Indexes for table `throne_streams`
--
ALTER TABLE `throne_streams`
  ADD PRIMARY KEY (`name`);

--
-- Indexes for table `throne_tokens`
--
ALTER TABLE `throne_tokens`
  ADD PRIMARY KEY (`token`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
