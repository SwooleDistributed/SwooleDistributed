<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT;

/**
 * Utilities for MQTT
 */
class Utility
{
    /**
     * Convert ASCII Invisible Character to Visible
     *
     * @param string $char
     * @param string $replace   Default Control Character to '.'
     * @return string
     */
    static public function ASCII2Visible($char, $replace='.')
    {
        $c = ord($char);
        if ($c >= 0x20 && $c <= 0x7F) {
            return $char;
        } else {
            return $replace;
        }
    }

    /**
     * Print string in Hex
     *
     * @param string $chars
     * @param bool   $return
     * @param int    $width
     * @param bool   $with_ascii
     * @return void|string
     */
    static public function PrintHex($chars, $return=false, $width=0, $with_ascii=false)
    {
        $output = '';

        $hex_str   = '';
        $ascii_str = '';

        $width = (int) $width;
        if (!$width) {
            for ($i=0; isset($chars[$i]); $i++) {
                $hex_str   .= sprintf('%02x ', ord($chars[$i]));
                $ascii_str .= sprintf('%s  ', self::ASCII2Visible($chars[$i], '.'));
            }

            $output .= "HEX DUMP:\t" . $hex_str . "\n";
            if ($with_ascii) {
                $output .= "ASCII CHR: \t" . $ascii_str . "\n";
            }

        } else {

            for ($i=0; isset($chars[$i]); $i++) {
                $hex_str   .= sprintf('%02x ', ord($chars[$i]));
                $ascii_str .= sprintf('%s', self::ASCII2Visible($chars[$i], '.'));
            }

            $ph = $pa = 0;
            $wh = 3;
            $wa = 1;
            $lwh = $wh * $width;
            $lwa = $wa * $width;

            do {
                $output .= "DUMP\t";
                $output .= str_pad(substr($hex_str, $ph, $lwh), $lwh, ' ');
                $output .= "\t";
                $output .= str_pad(substr($ascii_str, $pa, $lwa), $lwa, ' ');
                $output .= "\n";
                $ph += $lwh;
                $pa += $lwa;

                if (!isset($hex_str[$ph]) || !isset($ascii_str[$pa])) {
                    break;
                }
            } while (true);
        }

        if ($return) {
            return $output;
        } else {
            echo $output;
            return true;
        }
    }

    /**
     * Pack string with a 16-bit big endian length ahead.
     *
     * @param string $str  input string
     * @return string      returned string
     * @throws Exception
     */
    static public function PackStringWithLength($str)
    {
        $len = strlen($str);
        # UTF-8
        if (!self::ValidateUTF8($str)) {
            throw new Exception('Bad UTF-8 String');
        }
        return pack('n', $len) . $str;
    }

    /**
     * Unpack string
     *
     * @param string $str
     * @param int &  $pos
     * @return string
     */
    static public function UnpackStringWithLength($str, &$pos)
    {
        $length = self::ExtractUShort($str, $pos);

        $data = substr($str, $pos, $length);
        $pos += $length;

        return $data;
    }

    /**
     * Extract Unsigned Short from Buffer
     *
     * @param string $str
     * @param int &  $pos
     * @return int
     */
    static public function ExtractUShort($str, &$pos)
    {
        $bytes = substr($str, $pos, 2);
        $ushort = Utility::Word2UShort($bytes);
        $pos += 2;

        return $ushort;
    }

    /**
     * Check if message exceeds maximum length
     *
     * @param int $length
     * @throws Exception
     */
    static public function CheckMessageLength($length)
    {
        if ($length > Message::MAX_DATA_LENGTH) {
            throw new Exception('Too much data');
        }
    }

    /**
     * Encode Remaining Length
     *
     * @param int $length
     * @return string
     */
    static public function EncodeLength($length)
    {
        self::CheckMessageLength($length);

        $string = "";
        do{
            $digit = $length % 0x80;
            $length = $length >> 7;
            // if there are more digits to encode, set the top bit of this digit
            if ( $length > 0 ) $digit = ($digit | 0x80);
            $string .= chr($digit);
        } while ( $length > 0 );

        return $string;
    }

    /**
     * Decode Remaining Length
     *
     * @param string $msg
     * @param int &  $pos
     * @return int
     */
    static public function DecodeLength($msg, &$pos)
    {
        $multiplier = 1;
        $value = 0 ;
        do{
            $digit = ord($msg[$pos]);
            $value += ($digit & 0x7F) * $multiplier;
            $multiplier *= 0x80;
            $pos++;
        } while (($digit & 0x80) != 0);

        return $value;
    }

    /**
     * Check QoS
     *
     * @param int $qos
     * @throws Exception
     */
    static public function CheckQoS($qos)
    {
        if ($qos > 2 || $qos < 0) {
            throw new Exception('QoS must be an integer in (0,1,2).');
        }
    }
    /**
     * Check Client ID
     *
     * @param string $client_id
     * @throws Exception
     */
    static public function CheckClientID($client_id)
    {
        if (strlen($client_id) > 23) {
            throw new Exception('Client identifier exceeds 23 bytes.');
        }
    }

    /**
     * Check Packet Identifier
     *
     * @param int $msgid
     * @throws Exception
     */
    static public function CheckPacketIdentifier($msgid)
    {
        if (!is_int($msgid) || $msgid < 1 || $msgid > 65535) {
            throw new Exception('Packet identifier must be non-zero 16-bit.');
        }
    }

    /**
     * Check Keep Alive
     *
     * @param int $keepalive
     * @throws Exception
     */
    static public function CheckKeepAlive($keepalive)
    {
        if (!is_int($keepalive) || $keepalive < 1 || $keepalive > 65535) {
            throw new Exception('Keep alive must be non-zero 16-bit.');
        }
    }

    /**
     * Check Topic Name
     *
     * Based on 4.7 Topic Names and Topic Filters
     *
     * @param string $topic_name
     * @throws Exception
     */
    static public function CheckTopicName($topic_name)
    {
        if (!isset($topic_name[0]) || isset($topic_name[65535])) {
            throw new Exception('Topic name must be at 1~65535 bytes long');
        }

        self::ValidateUTF8($topic_name);

        if (false !== strpos($topic_name, chr(0))) {
            throw new Exception('null character is not allowed in topic');
        }
        if (false !== strpos($topic_name, '#')) {
            throw new Exception('# is not allowed in topic');
        }
        if (false !== strpos($topic_name, '+')) {
            throw new Exception('+ is not allowed in topic');
        }
    }

    /**
     * Check Topic Filter
     *
     * Based on 4.7 Topic Names and Topic Filters
     *
     * @param string $topic_filter
     * @throws Exception
     */
    static public function CheckTopicFilter($topic_filter)
    {
        if (!isset($topic_filter[0]) || isset($topic_filter[65535])) {
            throw new Exception('Topic filter must be at 1~65535 bytes long');
        }
        self::ValidateUTF8($topic_filter);

        if (false !== strpos($topic_filter, chr(0))) {
            throw new Exception('Null character is not allowed in topic');
        }

        $length = strlen($topic_filter);

        /*
         The multi-level wildcard character MUST be specified either on its own or following a topic level separator.
         In either case it MUST be the last character specified in the Topic Filter [MQTT-4.7.1-2].
         */
        if (($p = strpos($topic_filter, '#')) !== false) {
            if ($p != $length - 1) {
                throw new Exception('"#" MUST be the last char in topic filter');
            } else if ($length > 1 && $topic_filter[$length - 2] != '/') {
                throw new Exception('"#" MUST occupy an entire level of the filter');
            }
        }

        $levels = explode('/', $topic_filter);
        foreach ($levels as $l) {
            if ($l == '') {
                continue;
            } else if (strpos($l, '+') !== false && isset($l[1])) {
                /*
                 The single-level wildcard can be used at any level in the Topic Filter, including first and last levels.
                 Where it is used it MUST occupy an entire level of the filter [MQTT-4.7.1-3].
                 */
                throw new Exception('"+" MUST occupy an entire level of the filter');
            }
        }

        if ($topic_filter[0] == '#') {
            Debug::Log(Debug::DEBUG, 'If you want to subscribe topic begin with $, please subscribe both "#" and "$SOMETOPIC/#"');
        }
    }

    /**
     * Convert WORD to unsigned short
     *
     * @param string $word
     * @return int
     */
    static public function Word2UShort($word)
    {
        $c = unpack('n', $word);
        return $c[1];
    }

    /**
     * Parse command
     *
     * @param int $cmd
     * @return array array(message_type=>int, flags=>int)
     */
    static public function ParseCommand($cmd)
    {
        # check Message type
        $message_type = $cmd >> 4;
        $flags = $cmd & 0x0f;

        return array(
            'message_type'  =>  $message_type,
            'flags'         =>  $flags,
        );
    }

    /**
     * Parse Flags
     *
     * Currently used by PUBLISH only
     *
     * @param int $flags
     * @return array     array(dup=>int, qos=>int, retain=>int)
     */
    static public function ParseFlags($flags)
    {
        $dup = ($flags & 0x08) >> 3;
        $qos = ($flags & 0x06) >> 1;
        $retain = ($flags & 0x01);

        return array(
            'dup'           =>  $dup,
            'qos'           =>  $qos,
            'retain'        =>  $retain,
        );
    }

    /**
     * Unpack command
     *
     * @param int $cmd
     * @return array
     */
    static public function UnpackCommand($cmd)
    {
        # check Message type
        $message_type = $cmd >> 4;
        $dup = ($cmd & 0x08) >> 3;
        $qos = ($cmd & 0x06) >> 1;
        $retain = ($cmd & 0x01);

        return array(
            'message_type'  =>  $message_type,
            'dup'           =>  $dup,
            'qos'           =>  $qos,
            'retain'        =>  $retain,
        );
    }

    /**
     * Check if string is UTF-8
     *
     * @param string $string
     * @return bool
     * @throws Exception\BadUTF8
     */
    static public function ValidateUTF8($string)
    {
        $pop_10s = 0;

        $unicode_char = 0;

        for ($i=0; isset($string[$i]); $i++) {
            $c = ord($string[$i]);
            if ($pop_10s) {
                # Check if following chars in multibytes are not 10xxxxxx
                if (($c & 0xC0) != 0x80) {
                    throw new Exception\BadUTF8('Following characters must be 10xxxxxx');
                } else {
                    $unicode_char <<= 6;
                    $unicode_char |= $c & 0x3F;
                    --$pop_10s;
                }
            } else if (($c & 0x7F) == $c) {
                # single ASCII char
                $unicode_char = 0;

                /*
                 I tried mosquitto, it accepts \0 when publishing Message, no connection is closed.
                 No exception will be thrown here.

                 MQTT-1.5.3-2
                 A UTF-8 encoded string MUST NOT include an encoding of the null character U+0000.
                 If a receiver (Server or Client) receives a Control Packet containing U+0000 it MUST
                 close the Network Connection.

                 */
                continue;
            } else if (($c & 0xFE) == 0xFC) {
                # leading 1111110x
                $pop_10s = 5;

                $unicode_char = 0;
                $unicode_char |= $c & 0x01;
            } else if (($c & 0xFC) == 0xF8) {
                # leading 111110xx
                $pop_10s = 4;

                $unicode_char = 0;
                $unicode_char |= $c & 0x03;
            } else if (($c & 0xF8) == 0xF0) {
                # leading 11110xxx
                $pop_10s = 3;

                $unicode_char = 0;
                $unicode_char |= $c & 0x07;
            } else if (($c & 0xF0) == 0xE0) {
                # leading 1110xxxx
                $pop_10s = 2;

                $unicode_char = 0;
                $unicode_char |= $c & 0x0F;
            } else if (($c & 0xE0) == 0xC0) {
                # leading 110xxxxx
                $pop_10s = 1;

                $unicode_char = 0;
                $unicode_char |= $c & 0x1F;
            } else {
                throw new Exception\BadUTF8('Bad leading characters');
            }

            if ($unicode_char >= 0xD800 && $unicode_char <= 0xDFFF) {
                /*
                MQTT-1.5.3.1
                The character data in a UTF-8 encoded string MUST be well-formed UTF-8 as defined
                by the Unicode specification [Unicode] and restated in RFC 3629 [RFC3629]. In
                particular this data MUST NOT include encodings of code points between U+D800 and
                U+DFFF. If a Server or Client receives a Control Packet containing ill-formed UTF-8
                it MUST close the Network Connection [MQTT-1.5.3-1].

                 */
                throw new Exception\BadUTF8('U+D800 ~ U+DFFF CAN NOT be used in UTF-8');
            }
        }

        if ($pop_10s) {
            throw new Exception\BadUTF8('Missing UTF-8 following characters');
        }

        return true;
    }
}

# EOF