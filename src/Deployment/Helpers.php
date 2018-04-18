<?php

/**
 * FTP Deployment
 *
 * Copyright (c) 2009 David Grudl (https://davidgrudl.com)
 */

namespace Deployment;


/**
 * Helpers.
 */
class Helpers
{

	/**
	 * Computes hash.
	 * @param  string  absolute path
	 * @return string
	 */
	public static function hashFile($file)
	{
		if (filesize($file) > 5e6) {
			return md5_file($file);
		} else {
			$s = file_get_contents($file);
			if (preg_match('#^[\x09\x0A\x0D\x20-\x7E\x80-\xFF]*+\z#', $s)) {
				$s = str_replace("\r\n", "\n", $s);
			}
			return md5($s);
		}
	}


	/**
	 * Matches filename against patterns.
	 * @param  string   relative path
	 * @param  string[] patterns
	 * @return bool
	 */
	public static function matchMask($path, array $patterns, $isDir = false)
	{
		$res = false;
		$path = explode('/', ltrim($path, '/'));
		foreach ($patterns as $pattern) {
			$pattern = strtr($pattern, '\\', '/');
			if ($neg = substr($pattern, 0, 1) === '!') {
				$pattern = substr($pattern, 1);
			}

			if (strpos($pattern, '/') === false) { // no slash means base name
				if (fnmatch($pattern, end($path), FNM_CASEFOLD)) {
					$res = !$neg;
				}
				continue;

			} elseif (substr($pattern, -1) === '/') { // trailing slash means directory
				$pattern = trim($pattern, '/');
				if (!$isDir && count($path) <= count(explode('/', $pattern))) {
					continue;
				}
			}

			$parts = explode('/', ltrim($pattern, '/'));
			if (fnmatch(
				implode('/', $neg && $isDir ? array_slice($parts, 0, count($path)) : $parts),
				implode('/', array_slice($path, 0, count($parts))),
				FNM_CASEFOLD | FNM_PATHNAME
			)) {
				$res = !$neg;
			}
		}
		return $res;
	}


	/**
	 * Processes HTTP request.
	 * @return string
	 */
	public static function fetchUrl($url, &$error, array $postData = null)
	{
		if (extension_loaded('curl')) {
			$ch = curl_init($url);
			$options = [
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_FOLLOWLOCATION => 1,
			];
			if ($postData !== null) {
				$options[CURLOPT_POST] = true;
				$options[CURLOPT_POSTFIELDS] = http_build_query($postData, null, '&');
			}
			curl_setopt_array($ch, $options);
			$output = curl_exec($ch);
			if (curl_errno($ch)) {
				$error = curl_error($ch);
			} elseif (($code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) >= 400) {
				$error = "responds with HTTP code $code";
			}

		} else {
			$output = @file_get_contents($url, false, stream_context_create([
				'http' => $postData === null ? [] : [
					'method' => 'POST',
					'header' => 'Content-type: application/x-www-form-urlencoded',
					'content' => http_build_query($postData, null, '&'),
				],
			]));
			$error = $output === false
				? preg_replace("#^file_get_contents\(.*?\): #", '', error_get_last()['message'])
				: null;
		}
		return (string) $output;
	}


	/** @return string */
	public static function buildUrl(array $url)
	{
		return (isset($url['scheme']) ? $url['scheme'] . '://' : '')
			. (isset($url['user']) ? $url['user'] : '')
			. (isset($url['pass']) ? ':' . $url['pass'] : '')
			. (isset($url['user']) || isset($url['pass']) ? '@' : '')
			. (isset($url['host']) ? $url['host'] : '')
			. (isset($url['port']) ? ':' . $url['port'] : '')
			. (isset($url['path']) ? $url['path'] : '');
	}
}
