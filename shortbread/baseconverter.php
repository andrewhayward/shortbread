<?php

/**
 * Convert numbers from base 10 integers to base X strings and back again.
 *
 * An (almost direct) PHP port of Simon Willison's original Python BaseConverter
 * 
 * @author Andrew Hayward <mail@andrewhayward.net>
 * @see http://djangosnippets.org/snippets/1431/
 */

class BaseConverter {

	protected $characters;
	protected $digits = '0123456789';

	static $native = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	public function __construct ($base) {
		if (is_string($base)) {
			$this->characters = $base;
		} else {
			$base = (int) $base;
			$this->characters = substr(self::$native, 0, $base);
			if (strlen($this->characters) < $base)
				throw new OutOfRangeException('Maximum native base is '.strlen(self::$native).'.');
		}
	}

	public function from_decimal ($number) {
		return self::convert($number, $this->digits, $this->characters);
	}

	public function to_decimal ($str) {
		return intval(self::convert($str, $this->characters, $this->digits));
	}

	static function convert ($input, $from, $to) {
		// Based on http://code.activestate.com/recipes/111286/

		$str = strval($input);

		if ($str[0] == '-') {
			$str = substr($str, 1);
			$negative = true;
		} else {
			$negative = false;
		}

		$i = 0;
		foreach (str_split($str) as $char) {
			$i = $i * strlen($from) + strpos($from, $char);
		}

		if ($i == 0) {
			$result = $to[0];
		} else {
			$result = array();
			while ($i > 0) {
				$digit = $i % strlen($to);
				$result[] = $to[$digit];
				$i = intval($i / strlen($to));
			}
			if ($negative) $result[] = '-';
			$result = implode('', array_reverse($result));
		}

		return $result;
	}
}
