<?php

namespace Neurame\Utils;

class Logger
{
	private static $file;

	public static function init($filename = null)
	{
		self::$file = $filename ?: WP_CONTENT_DIR . '/neurame-debug.log';

		if (!file_exists(self::$file)) {
			file_put_contents(self::$file, '');
			chmod(self::$file, 0644);
		}
	}

	public static function log($level, $message)
	{
		if (!defined('NEURAMEAI_DEBUG_LOG') || !NEURAMEAI_DEBUG_LOG) return;

		$timestamp = current_time('Y-m-d H:i:s');
		$formatted = "[$timestamp][$level] $message\n";

		if (is_writable(dirname(self::$file))) {
			file_put_contents(self::$file, $formatted, FILE_APPEND);
		}
	}

	public static function info($msg)
	{
		self::log('INFO', $msg);
	}

	public static function warning($msg)
	{
		self::log('WARNING', $msg);
	}

	public static function error($msg)
	{
		self::log('ERROR', $msg);
	}

	public static function debug($msg)
	{
		self::log('DEBUG', $msg);
	}
}
