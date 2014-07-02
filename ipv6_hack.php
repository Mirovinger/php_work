<?php
/**
 * This script will create a fake IPv4 for IPv6 users based on the 52 first bits of their IP.
 *
 * @author  NewEraCracker
 * @version 1.0.8
 * @date    2014/07/02
 * @license Public Domain
 *
 * It is usually unlikely for someone to obtain a different generated IPv4 without access
 * to more than a /52.
 */

$_SERVER['REMOTE_ADDR'] = NewEra_IPv6Hack::all_to_ipv4($_SERVER['REMOTE_ADDR']);

class NewEra_IPv6Hack
{
	/** @Link : http://php.net/manual/en/function.inet-pton.php */
	public static function ip_pack($ip)
	{
		if(strpos($ip, '.') !== false)
		{
			// Pack IPv4
			$ip = trim($ip, ':f');
			$ip = pack('N', ip2long($ip));

			return $ip;
		}
		elseif(strpos($ip, ':') !== false)
		{
			// Expand IPv6
			$ip = self::ipv6_expand($ip);

			// Pack IPv6
			$ip = pack('H'.strlen($ip), $ip);

			return $ip;
		}

		return false;
	}

	/** @Link : http://php.net/manual/en/function.inet-ntop.php */
	public static function ip_unpack($ip)
	{
		if(strlen($ip) == 4)
		{
			// Unpack IPv4
			list(, $ip) = unpack('N', $ip);
			$ip = long2ip($ip);
		}
		elseif(strlen($ip) == 16)
		{
			// Unpack IPv6
			$ip = bin2hex($ip);
			$ip = substr(chunk_split($ip, 4, ':'), 0, -1);

			// Compact IPv6
			$ip  = explode(':',$ip);
			$res = '';
			for($i = (count($ip)-1); $i >= 0; $i--)
			{
				$seg = $ip[$i];

				while($seg != '' && $seg[0] == '0')
				{
					$seg = substr($seg, 1);
				}

				if($seg != '')
				{
					$res = $seg.($res==''?'':':').$res;
				}
				else
				{
					if(strpos($res, '::') === false)
					{
						// @Hack : Check iteration number to make sure ::1 case is handled
						if($res != '' && $res[0] == ':' && $i > 0)
						{
							continue;
						}
						$res = ':'.$res;
						continue;
					}
					$res = '0'.($res==''?'':':').$res;
				}
			}
			$ip = $res;
		}
		else
		{
			return false;
		}

		return $ip;
	}

	/** Expand an IPv6 address */
	public static function ipv6_expand($ip)
	{
		$ip = explode(':', $ip);
		$res = '';
		$expand = true;
		foreach($ip as $seg)
		{
			if($seg == '' && $expand)
			{
				// This will expand a compacted IPv6
				$res .= str_pad('', (((8 - count($ip)) + 1) * 4), '0', STR_PAD_LEFT);

				// We only expand once, else it would cause troubles with ::1 or ffff::
				$expand = false;
			}
			else
			{
				// This will pad to ensure each IPv6 part has 4 digits.
				$res .= str_pad($seg, 4, '0', STR_PAD_LEFT);
			}
		}

		return $res;
	}

	/** Shift an IPv6 to right (IPv6 >> 1). This will be handy to generate a fake IPv4 */
	public static function ipv6_shift_right($ip)
	{
		$ip = self::ipv6_expand($ip);
		$ip = substr($ip, -1).substr($ip, 0, -1);
		$ip = substr(chunk_split($ip, 4, ':'), 0, -1);

		return $ip;
	}

	/** Create a fake IPv4 address from a given IPv6 address */
	public static function ipv6_to_ipv4($ip)
	{
		if(strpos($ip, ':') === false || strpos($ip, '.') !== false)
		{
			return false;
		}

		$ip = self::ipv6_shift_right($ip);
		$ip = self::ip_pack($ip);

		// First 8 bits of IPv4 will be:
		// - The last 4 bits of unshifted IPv6, all set to true via mask
		// - The first 4 bits of unshifted IPv6, all in their original state via mask
		// This ensures an IPv4 in Class E range (240.0.0.0 - 255.255.255.255)
		$ipv4 = chr(ord($ip[0]) | 0xf0);

		for($i=1;$i<7;$i+=2)
		{
			// Convert 48 bits of IPv6 in last 24 bits of IPv4 via XOR
			$ipv4 .= chr(ord($ip[$i]) ^ ord($ip[$i+1]));
		}

		return self::ip_unpack($ipv4);
	}

	/** Convert a V4overV6 to IPv4 */
	public static function v4overv6_to_ipv4($ip)
	{
		if(strpos($ip, '.') !== false)
		{
			$ip = trim($ip, ':f');
			return $ip;
		}

		return false;
	}

	/** This will test if it is a V4overV6 or an IPv6 and do the convertion */
	public static function all_to_ipv4($ip)
	{
		$v4overv6_test = self::v4overv6_to_ipv4($ip);

		if($v4overv6_test !== false)
		{
			return $v4overv6_test;
		}

		return self::ipv6_to_ipv4($ip);
	}
}
?>