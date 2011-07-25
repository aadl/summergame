-- phpMyAdmin SQL Dump
-- version 3.3.7
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jul 25, 2011 at 03:20 PM
-- Server version: 5.0.51
-- PHP Version: 5.2.6-1+lenny13

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

-- --------------------------------------------------------

--
-- Table structure for table `sg_badges`
--

CREATE TABLE `sg_badges` (
  `bid` int(10) unsigned NOT NULL auto_increment,
  `image` varchar(32) default NULL,
  `title` varchar(32) default NULL,
  `description` varchar(128) default NULL,
  `formula` varchar(128) default NULL,
  `points` int(10) unsigned NOT NULL,
  `public` tinyint(1) unsigned NOT NULL default '1',
  PRIMARY KEY  (`bid`),
  KEY `public` (`public`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sg_game_codes`
--

CREATE TABLE `sg_game_codes` (
  `code_id` int(10) unsigned NOT NULL auto_increment,
  `creator_uid` int(10) unsigned NOT NULL,
  `created` int(10) unsigned NOT NULL,
  `valid_start` int(10) unsigned NOT NULL,
  `valid_end` int(10) unsigned NOT NULL,
  `text` varchar(32) NOT NULL,
  `description` varchar(128) NOT NULL,
  `points` int(10) unsigned NOT NULL,
  `max_redemptions` int(10) unsigned NOT NULL,
  `num_redemptions` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`code_id`),
  UNIQUE KEY `text` (`text`),
  KEY `created` (`created`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sg_ledger`
--

CREATE TABLE `sg_ledger` (
  `lid` int(10) unsigned NOT NULL auto_increment,
  `pid` int(10) unsigned NOT NULL,
  `points` int(11) NOT NULL,
  `code_text` varchar(32) default NULL,
  `description` varchar(256) default NULL,
  `timestamp` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`lid`),
  KEY `pid` (`pid`),
  KEY `code_text` (`code_text`),
  KEY `timestamp` (`timestamp`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sg_players`
--

CREATE TABLE `sg_players` (
  `pid` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(64) default NULL,
  `nickname` varchar(64) default NULL,
  `phone` bigint(20) unsigned default NULL,
  `uid` int(10) unsigned NOT NULL,
  `gamecard` varchar(16) default NULL,
  `show_leaderboard` tinyint(1) unsigned NOT NULL,
  `show_myscore` tinyint(1) unsigned NOT NULL,
  `show_titles` tinyint(1) unsigned NOT NULL,
  `agegroup` enum('youth','teen','adult') NOT NULL default 'adult',
  `school` varchar(64) default NULL,
  `grade` int(10) unsigned default NULL,
  `prizes` varchar(64) default NULL,
  PRIMARY KEY  (`pid`),
  KEY `phone` (`phone`,`uid`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sg_players_badges`
--

CREATE TABLE `sg_players_badges` (
  `pid` int(10) unsigned NOT NULL,
  `bid` int(10) unsigned NOT NULL,
  `timestamp` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`pid`,`bid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sg_schools`
--

CREATE TABLE `sg_schools` (
  `sch_id` int(3) unsigned NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  PRIMARY KEY  (`sch_id`),
  KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `sg_trivia_correct`
--

CREATE TABLE `sg_trivia_correct` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `phone` bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sg_trivia_guesses`
--

CREATE TABLE `sg_trivia_guesses` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `guess` varchar(140) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
