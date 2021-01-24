<?php
/**
 * Class Byte
 *
 * @created      25.11.2015
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2015 Smiley
 * @license      MIT
 */

namespace chillerlan\QRCode\Data;

use chillerlan\QRCode\Common\{BitBuffer, Mode};

use function ord;

/**
 * Byte mode, ISO-8859-1 or UTF-8
 *
 * ISO/IEC 18004:2000 Section 8.3.4
 * ISO/IEC 18004:2000 Section 8.4.4
 */
final class Byte extends QRDataModeAbstract{

	protected static int $datamode = Mode::DATA_BYTE;

	/**
	 * @inheritdoc
	 */
	public function getLengthInBits():int{
		return $this->getCharCount() * 8;
	}

	/**
	 * @inheritdoc
	 */
	public static function validateString(string $string):bool{
		return !empty($string);
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

		while($i < $len){
			$bitBuffer->put(ord($this->data[$i]), 8);
			$i++;
		}

	}

	/**
	 * @inheritdoc
	 *
	 * @throws \chillerlan\QRCode\Data\QRCodeDataException
	 */
	public static function decodeSegment(BitBuffer $bitBuffer, int $versionNumber):string{
		$length = $bitBuffer->read(Mode::getLengthBitsForVersion(self::$datamode, $versionNumber));

		if($bitBuffer->available() < 8 * $length){
			throw new QRCodeDataException('not enough bits available');
		}

		$readBytes = '';

		for($i = 0; $i < $length; $i++){
			$readBytes .= \chr($bitBuffer->read(8));
		}

		return $readBytes;
	}

}
