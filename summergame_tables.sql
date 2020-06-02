-- phpMyAdmin SQL Dump
-- version 4.0.4.1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Sep 18, 2015 at 02:44 PM
-- Server version: 5.0.84-log
-- PHP Version: 5.2.6-1+lenny13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Table structure for table `sg_badges`
--

CREATE TABLE IF NOT EXISTS `sg_badges` (
  `bid` int(10) unsigned NOT NULL auto_increment,
  `image` varchar(64) default NULL,
  `title` varchar(64) default NULL,
  `description` varchar(8000) default NULL,
  `difficulty` enum('Beginner','Advanced','Expert') NOT NULL default 'Advanced',
  `formula` varchar(2000) default NULL,
  `reveal` varchar(32) NOT NULL,
  `points` int(10) unsigned NOT NULL,
  `points_override` varchar(32) NOT NULL,
  `game_term` varchar(32) NOT NULL,
  `game_term_override` varchar(32) NOT NULL,
  `active` tinyint(1) unsigned NOT NULL default '1',
  `email_message` text,
  `email_attachment` varchar(256) default NULL,
  PRIMARY KEY  (`bid`),
  KEY `public` (`active`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sg_game_codes`
--

CREATE TABLE IF NOT EXISTS `sg_game_codes` (
  `code_id` int(10) unsigned NOT NULL auto_increment,
  `creator_uid` int(10) unsigned NOT NULL,
  `created` int(10) unsigned NOT NULL,
  `valid_start` int(10) unsigned NOT NULL,
  `valid_end` int(10) unsigned NOT NULL,
  `text` varchar(255) NOT NULL,
  `description` varchar(2000) NOT NULL,
  `hint` varchar(2000) NOT NULL,
  `points` int(10) unsigned NOT NULL,
  `points_override` int(10) NOT NULL,
  `diminishing` tinyint(1) unsigned NOT NULL default '0',
  `max_redemptions` int(10) unsigned NOT NULL,
  `num_redemptions` int(10) unsigned NOT NULL default '0',
  `game_term` varchar(32) NOT NULL,
  `game_term_override` varchar(32) NOT NULL,
  `everlasting` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`code_id`),
  UNIQUE KEY `text` (`text`),
  KEY `created` (`created`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sg_ledger`
--

CREATE TABLE IF NOT EXISTS `sg_ledger` (
  `lid` int(10) unsigned NOT NULL auto_increment,
  `pid` int(10) unsigned NOT NULL,
  `points` int(11) NOT NULL,
  `type` varchar(255) default NULL,
  `metadata` varchar(2000) NOT NULL,
  `description` varchar(2000) default NULL,
  `game_term` varchar(32) NOT NULL,
  `timestamp` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`lid`),
  KEY `pid` (`pid`),
  KEY `code_text` (`type`),
  KEY `timestamp` (`timestamp`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sg_players`
--

CREATE TABLE IF NOT EXISTS `sg_players` (
  `pid` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(64) default NULL,
  `nickname` varchar(64) default NULL,
  `phone` bigint(20) unsigned default NULL,
  `uid` int(10) unsigned default NULL,
  `gamecard` varchar(16) default NULL,
  `show_leaderboard` tinyint(1) unsigned NOT NULL,
  `show_myscore` tinyint(1) unsigned NOT NULL,
  `show_titles` tinyint(1) unsigned NOT NULL,
  `agegroup` enum('youth','teen','adult') NOT NULL default 'adult',
  `school` varchar(64) default NULL,
  `grade` int(10) unsigned default NULL,
  `friend_code` varchar(32) default NULL,
  PRIMARY KEY  (`pid`),
  KEY `phone` (`phone`,`uid`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sg_players_badges`
--

CREATE TABLE IF NOT EXISTS `sg_players_badges` (
  `pid` int(10) unsigned NOT NULL,
  `bid` int(10) unsigned NOT NULL,
  `timestamp` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`pid`,`bid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sg_players_teams`
--

CREATE TABLE IF NOT EXISTS `sg_players_teams` (
  `pid` int(10) unsigned NOT NULL,
  `tid` int(10) unsigned NOT NULL,
  KEY `pid` (`pid`,`tid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sg_schools`
--

CREATE TABLE IF NOT EXISTS `sg_schools` (
  `sch_id` int(3) unsigned NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  PRIMARY KEY  (`sch_id`),
  KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `sg_teams`
--

CREATE TABLE IF NOT EXISTS `sg_teams` (
  `tid` int(10) unsigned NOT NULL auto_increment,
  `uid` int(10) unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  `description` text NOT NULL,
  `code` varchar(32) default NULL,
  PRIMARY KEY  (`tid`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sg_trivia_correct`
--

CREATE TABLE IF NOT EXISTS `sg_trivia_correct` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `phone` bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `phone` (`phone`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sg_trivia_guesses`
--

CREATE TABLE IF NOT EXISTS `sg_trivia_guesses` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `guess` varchar(140) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------
--
-- Badge Table Alters for 2016
--

ALTER TABLE `sg_badges` ADD `level` TINYINT NOT NULL AFTER `description`;
ALTER TABLE `sg_badges` ADD `type` VARCHAR( 64 ) NOT NULL AFTER `level`;
UPDATE `sg_badges` SET `level` = 1 WHERE `difficulty` = 'Beginner';
UPDATE `sg_badges` SET `level` = 2 WHERE `difficulty` = 'Advanced';
UPDATE `sg_badges` SET `level` = 3 WHERE `difficulty` = 'Expert';

-- ---------------------------------------------------------
--
-- Game Code Table Alter for 2020
--

ALTER TABLE `sg_game_codes` ADD `link` VARCHAR(255) NOT NULL, ADD INDEX (`link`);
