<?php

class ethash
{

    private $cacheSeeds = array();

    private $cacheBySeed = array();

    private $maxItems = 10;

    static $WORD_BYTES = 4;
 // bytes in word
    static $DATASET_BYTES_INIT = 1073741824;
 // bytes in dataset at genesis
    static $DATASET_BYTES_GROWTH = 8388608;
 // growth per epoch (~7 GB per year)
    static $CACHE_BYTES_INIT = 16777216;
 // Size of the dataset relative to the cache @todo
    static $CACHE_BYTES_GROWTH = 131072;
 // Size of the dataset relative to the cache
    static $EPOCH_LENGTH = 30000;
 // blocks per epoch
    static $MIX_BYTES = 128;
 // width of mix
    static $HASH_BYTES = 64;
 // hash length in bytes
    static $DATASET_PARENTS = 256;
 // number of parents of each dataset element
    static $CACHE_ROUNDS = 3;
 // number of rounds in cache production
    static $ACCESSES = 64;
 // number of accesses in hashimoto loop
    static $FNV_PRIME = 0x01000193;

    public function __construct()
    {
        $this->cacheSeeds[] = pack('H*', '0000000000000000000000000000000000000000000000000000000000000000');
    }

    /**
     * Check if the proof-of-work of the block is valid.
     *
     * @param
     *            $pendingBlockNumber
     * @param
     *            $hashNoNonce
     * @param
     *            $mixDigest
     * @param
     *            $nonce
     * @param
     *            $difficulty
     *            
     * @return bool true or false
     */
    public function verify($pendingBlockNumber, $hashNoNonce, $mixDigest, $nonce, $difficulty)
    {
        if (strlen($mixDigest) !== 32 || strlen($hashNoNonce) !== 32 || strlen($nonce) !== 8) {
            return false;
        }
        
        $dagCache = $this->getCache($pendingBlockNumber);
    }

    public function getCache($blockNumber)
    {
        $seedLen = count($this->cacheSeeds);
        // get epoch
        $epochId = floor($blockNumber / self::$EPOCH_LENGTH);
        
        echo $epochId;
        
        for ($i = $seedLen; $i <= $epochId; $i ++) {
            
            $sponge = SHA3::init(SHA3::SHA3_256);
            
            $salt = $this->hex2String($this->cacheSeeds[$i - 1]);
            
            $sponge->absorb($salt);
            
            $this->cacheSeeds[] = $sponge->squeeze();
        }
        
        $seed = $this->cacheSeeds[$epochId];
        
        if (isset($this->cacheBySeed[$seed])) {
            
            $this->cacheBySeed[$seed]['fetchtime'] = time();
            
            return $this->cacheBySeed[$seed];
        }
        
        $c = $this->makeCache($blockNumber);
        
        $this->cacheBySeed[$seed] = array(
            'val' => $c,
            'fetchtime' => time()
        );
        
        // remove last recently accessed
        if (count($this->cacheBySeed) > $this->maxItems) {
            $mintime = time();
            $seedkey = false;
            
            foreach ($this->cacheBySeed as $key => $val) {
                if ($val['fetchtime'] < $mintime) {
                    $mintime = $val['fetchtime'];
                    $seedkey = $key;
                }
            }
            
            if (false !== $seedkey) {
                unset($this->cacheBySeed[$seedkey]);
            }
        }
        
        return $c;
    }

    public function makeCache($blockNumber)
    {
        $seedLen = count($this->cacheSeeds);
        // get epoch
        $epochId = floor($blockNumber / self::$EPOCH_LENGTH);
        
        for ($i = $seedLen; $i <= $epochId; $i ++) {
            
            $sponge = SHA3::init(SHA3::SHA3_256);
            
            $salt = $this->hex2String($this->cacheSeeds[$i - 1]);
            
            $sponge->absorb($salt);
            
            $this->cacheSeeds[] = $sponge->squeeze();
        }
        
        $seed = $this->cacheSeeds[$epochId];
        
        $n = floor($this->getCacheSize($blockNumber) / self::$HASH_BYTES);
        
        return $this->_getCache($seed, $n);
    }

    public function _getCache($seed, $n)
    {
        $o = array();
        
        $firstSeedHash = $this->sha3_512($seed);
        
        $o[] = $firstSeedHash['hash'];
        
        $lastHex = $firstSeedHash['hex'];
        
        echo "n:" . $n;
        
        //$n=10;
        
        for ($i = 1; $i < $n; $i ++) {
            
            $tempSeedHash = $this->sha3_512($lastHex);
            
            $o[] = $tempSeedHash['hash'];
            
            $lastHex = $tempSeedHash['hex'];
        }
        
        for ($i = 0; $i < self::$CACHE_ROUNDS; $i ++) {
            
            for ($j = 0; $j < $n; $j ++) {
                
                $tempKey = $o[$j][0] % $n;
                
                $fixKey = ($j + $n - 1) % $n;
                
                $newo='';
                
                for ($k=0;$k<16;$k++){
                    $newo.=pack('V',$o[$tempKey][$k]^$o[$fixKey][$k]);
                }
                echo "tempkey:".$tempKey."\r\n";
                echo "fixKey:".$fixKey."\r\n";
                $newoHash=$this->sha3_512($newo);
                
                $o[$j] = $newoHash['hash'];
            }
        }
        
        //print_r($o);
        
        return $o;
    }

    public function sha3_512($x)
    {
        $sponge = SHA3::init(SHA3::SHA3_512);
        
        $sponge->absorb($x);
        
        $hex = $sponge->squeeze();
        
        return array(
            'hex' => $hex,
            'hash' => $this->deserializeHash($hex)
        );
    }

    public function sha3_256($x)
    {
        $sponge = SHA3::init(SHA3::SHA3_256);
        
        $sponge->absorb($x);
        $hex = $sponge->squeeze();
        
        return array(
            'hex' => $hex,
            'hash' => $this->deserializeHash($hex)
        );
    }

    public function deserializeHash($hash)
    {
        $intAry = array();
        
        $len = strlen($hash);
        
        for ($i = 0; $i < $len; $i += self::$WORD_BYTES) {
            $temp = $this->letterenbian(substr($hash, $i, self::$WORD_BYTES));
            
            $intAry[] = $this->unpackUint32($temp);
        }
        
        return $intAry;
    }

    private function unpackUint32($hex)
    {
        $d = ord($hex[3]);
        
        $d |= ord($hex[2]) << 8;
        
        $d |= ord($hex[1]) << 16;
        
        $d |= ord($hex[0]) << 24;
        
        return $d;
    }

    public function getCacheSize($blockNumber)
    {
        $epochId = floor($blockNumber / self::$EPOCH_LENGTH);
        
        $sz = self::$CACHE_BYTES_INIT + (self::$CACHE_BYTES_GROWTH * $epochId);
        $sz -= self::$HASH_BYTES;
        
        while (! $this->isPrimeNum(floor($sz / self::$HASH_BYTES))) {
            $sz -= 2 * self::$HASH_BYTES;
        }
        
        return $sz;
    }

    public function letterenbian($n)
    {
        $return = '';
        for ($i = 0; $i < strlen($n); $i += 1) {
            $return = substr($n, $i, 1) . $return;
        }
        return $return;
    }

    public function getFullSize($blockNumber)
    {
        $epochId = floor($blockNumber / self::$EPOCH_LENGTH);
        
        $sz = self::$DATASET_BYTES_INIT + (self::$DATASET_BYTES_GROWTH * $epochId);
        
        $sz -= self::$MIX_BYTES;
        
        while (! $this->isPrimeNum(floor($sz / self::$MIX_BYTES))) {
            $sz -= 2 * self::$MIX_BYTES;
        }
        
        return $sz;
    }

    public function isPrimeNum($x)
    {
        $max = floor(sqrt($x));
        
        if ($max > 2) {
            for ($i = 2; $i < $max; $i ++) {
                if (! ($x % $i)) {
                    return false;
                }
            }
        }
        return true;
    }

    public function string2Hex($string)
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i ++) {
            $hex .= dechex(ord($string[$i]));
        }
        return $hex;
    }

    public function hex2String($hex)
    {
        return $hex;
        $string = '';
        for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
            $string .= chr(hexdec($hex[$i] . $hex[$i + 1]));
        }
        return $string;
    }
}