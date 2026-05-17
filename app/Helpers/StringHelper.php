<?php


namespace App\Helpers;

class StringHelper
{
	public static function transliteration(string $string): string
	{
		$string = mb_strtolower(trim(self::toStr($string)));

		$aConfig = array(
			'spaceSeparator' => '-',
			'transliteration' => array(
				'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
				'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm',
				'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
				'ф' => 'f', 'х' => 'x', 'ч' => 'ch', 'ц' => 'cz', 'ш' => 'sh', 'щ' => 'shh', 'ъ' => '',
				'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya'
			)
		);

		$spaceSeparator = $aConfig['spaceSeparator'];

		$uml_search = array('À','Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ',
		'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ð', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ',
		'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'ĸ',
		'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ŋ', 'ŋ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š',
		'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'Ǆ', 'ǅ', 'ǆ', 'Ǉ', 'ǈ', 'ǉ', 'Ǌ', 'ǋ', 'ǌ',
		'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'ǝ', 'Ǟ', 'ǟ', 'Ǡ', 'ǡ', 'Ǣ', 'ǣ', 'Ǥ', 'ǥ', 'Ǧ', 'ǧ', 'Ǩ', 'ǩ', 'Ǫ', 'ǫ', 'Ǭ', 'ǭ', 'Ǯ', 'ǯ', 'ǰ', 'Ǳ', 'ǲ', 'ǳ',
		'Ǵ', 'ǵ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ', 'Ȁ', 'ȁ', 'Ȃ', 'ȃ', 'Ȅ', 'ґ', 'є', 'і', 'ї', 'Ґ', 'Є', 'І', 'Ї', 'ô');

		$uml_replace = array('a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'd', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'ss', 'a', 'a', 'a', 'a', 'a', 'a', 'ae',
		'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'd', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'a', 'a', 'a', 'a', 'a', 'a', 'c', 'c', 'c', 'c', 'c', 'c', 'c', 'c', 'd', 'd', 'd', 'd',
		'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'g', 'g', 'g', 'g', 'g', 'g', 'g', 'g', 'h', 'h', 'h', 'h', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'j', 'j', 'k', 'k', 'k',
		'l', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'ce', 'ce', 'r', 'r', 'r', 'r', 'r', 'r', 's', 's', 's', 's', 's', 's', 's', 's',
		't', 't', 't', 't', 't', 't', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'w', 'w', 'y', 'y', 'y', 'z', 'z', 'z', 'z', 'z', 'z', 'dz', 'dz', 'dz', 'lj', 'lj', 'kj', 'nj', 'nj', 'nj',
		'a', 'a', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'e', 'a', 'a', 'a', 'a', 'ae', 'ae', 'g', 'g', 'g', 'g', 'k', 'k', 'o', 'o', 'o', 'o', 'z', 'z', 'z', 'dz', 'dz', 'dz',
		'g', 'g', 'a', 'a', 'ae', 'ae', 'o', 'o', 'a', 'a', 'a', 'a', 'e', 'g', 'ye', 'i', 'yi', 'G', 'Ye', 'I', 'I', 'o');

		$string = str_replace($uml_search, $uml_replace, $string);

		// Transliteration
		$string = str_replace(array_keys($aConfig['transliteration']), array_values($aConfig['transliteration']), $string);

		// Space and no-break space (0x00A0)
		$string = str_replace(array(' ', ' '), $spaceSeparator, $string);

		// Cut another chars
		$string = preg_replace('/[^a-zA-Z0-9\-_]/u', '', $string);

		// Rerplace double $spaceSeparator
		while (mb_strpos($string, $spaceSeparator . $spaceSeparator) !== FALSE)
		{
			$string = str_replace($spaceSeparator . $spaceSeparator, $spaceSeparator, $string);
		}

		return $string;
	}

	public static function hasCyrillic($string) {
        return preg_match('/[А-Яа-яЁё]/u', $string) === 1;
    }

    public static function toStr($mixed)
	{
		if (is_array($mixed))
		{
			return 'Array';
		}
		elseif (is_object($mixed) && !method_exists($mixed, '__toString'))
		{
			return 'Object';
		}

		return strval($mixed);
	}

	public static function formatLastModified($number)
	{

		return $number !== false ? date('d.m.Y H:i', $number) : 'Unknown';
	}

	public static function formatSize($bytes)
    {
        if ($bytes < 1024) return $bytes . ' B';
        elseif ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        elseif ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        else return round($bytes / 1073741824, 1) . ' GB';
    }

	public static function reverseBackSlashes(string $string)
	{

		return str_replace('\\', '/', $string);
	}
}
