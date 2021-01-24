<?php
/**
 * Class Number
 *
 * @created      26.11.2015
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2015 Smiley
 * @license      MIT
 */

namespace chillerlan\QRCode\Data;

use chillerlan\QRCode\Common\{BitBuffer, Mode};

use function array_flip, ceil, ord, sprintf, str_split, substr;

/**
 * Numeric mode: decimal digits 0 to 9
 *
 * ISO/IEC 18004:2000 Section 8.3.2
 * ISO/IEC 18004:2000 Section 8.4.2
 */
final class Number extends QRDataModeAbstract{

	/**
	 * @var int[]
	 */
	protected const CHAR_MAP_NUMBER = [
		'0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
	];

	protected static int $datamode = Mode::DATA_NUMBER;

	/**
	 * @inheritdoc
	 */
	public function getLengthInBits():int{
		return (int)ceil($this->getCharCount() * (10 / 3));
	}

	/**
	 * @inheritdoc
	 */
	public static function validateString(string $string):bool{

		foreach(str_split($string) as $chr){
			if(!isset(self::CHAR_MAP_NUMBER[$chr])){
				return false;
			}
		}

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function write(BitBuffer $bitBuffer, int $versionNumber):void{
		$len = $this->getCharCount();

		$bitBuffer
			->put($this::$datamode, 4)
			->put($len, Mode::getLengthBitsForVersion($this::$datamode, $versionNumber))
		;

		$i = 0;

		// encode numeric triplets in 10 bits
		while($i + 2 < $len){
			$bitBuffer->put($this->parseInt(substr($this->data, $i, 3)), 10);
			$i += 3;
		}

		if($i < $len){

			// encode 2 remaining numbers in 7 bits
			if($len - $i === 2){
				$bitBuffer->put($this->parseInt(substr($this->data, $i, 2)), 7);
			}
			// encode one remaining number in 4 bits
			elseif($len - $i === 1){
				$bitBuffer->put($this->parseInt(substr($this->data, $i, 1)), 4);
			}

		}

	}

	/**
	 * get the code for the given numeric string
	 *
	 * @throws \chillerlan\QRCode\Data\QRCodeDataException on an illegal character occurence
	 */
	protected function parseInt(string $string):int{
		$num = 0;

		foreach(str_split($string) as $chr){
			$c = ord($chr);

			if(!isset(self::CHAR_MAP_NUMBER[$chr])){
				throw new QRCodeDataException(sprintf('illegal char: "%s" [%d]', $chr, $c));
			}

			$c   = $c - 48; // ord('0')
			$num = $num * 10 + $c;
		}

		return $num;
	}

	/**
	 * @inheritdoc
	 *
	 * @throws \chillerlan\QRCode\Data\QRCodeDataException
	 */
	public static function decodeSegment(BitBuffer $bitBuffer, int $versionNumber):string{
		$length  = $bitBuffer->read(Mode::getLengthBitsForVersion(self::$datamode, $versionNumber));
		$charmap = array_flip(self::CHAR_MAP_NUMBER);

		// @todo
		$toNumericChar = function(int $ord) use ($charmap):string{

			if(isset($charmap[$ord])){
				return $charmap[$ord];
			}

			throw new QRCodeDataException('invalid character value: '.$ord);
		};

		$result = '';
		// Read three digits at a time
		while($length >= 3){
			// Each 10 bits encodes three digits
			if($bitBuffer->available() < 10){
				throw new QRCodeDataException('not enough bits available');
			}

			$threeDigitsBits = $bitBuffer->read(10);

			if($threeDigitsBits >= 1000){
				throw new QRCodeDataException('error decoding numeric value');
			}

			$result .= $toNumericChar($threeDigitsBits / 100);
			$result .= $toNumericChar(($threeDigitsBits / 10) % 10);
			$result .= $toNumericChar($threeDigitsBits % 10);

			$length -= 3;
		}

		if($length === 2){
			// Two digits left over to read, encoded in 7 bits
			if($bitBuffer->available() < 7){
				throw new QRCodeDataException('not enough bits available');
			}

			$twoDigitsBits = $bitBuffer->read(7);

			if($twoDigitsBits >= 100){
				throw new QRCodeDataException('error decoding numeric value');
			}

			$result .= $toNumericChar($twoDigitsBits / 10);
			$result .= $toNumericChar($twoDigitsBits % 10);
		}
		elseif($length === 1){
			// One digit left over to read
			if($bitBuffer->available() < 4){
				throw new QRCodeDataException('not enough bits available');
			}

			$digitBits = $bitBuffer->read(4);

			if($digitBits >= 10){
				throw new QRCodeDataException('error decoding numeric value');
			}

			$result .= $toNumericChar($digitBits);
		}

		return $result;
	}

}
