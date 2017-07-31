<?php
include 'bigInt.php';

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
    static $FNV_PRIME = 16777619;

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
    public function verify($pendingBlockNumber, $hashNoNonce, $mixDigest, $nonce, $difficulty, $diffNetwork = false)
    {
        if (strlen($mixDigest) !== 32 || strlen($hashNoNonce) !== 32 || strlen($nonce) !== 8) {
            return false;
        }
        
        $dagCache = $this->getCache($pendingBlockNumber);
        
        $miningOutput = $this->hashimotoLight($pendingBlockNumber, $dagCache, $hashNoNonce, $nonce);
        
        if ($mixDigest != $miningOutput['digest']) {
            
            return false;
        }
        
        $hashDiff = new Math_BigInteger($miningOutput['result'], 16);
        
        $pow2_256 = new Math_BigInteger('ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff', 16);
        
        if (false != $diffNetwork) {
            $diffNetwork = new Math_BigInteger($diffNetwork, 16);
            
            // first compare network difficulty
            list ($quotient, $remainder) = $pow2_256->divide($diffNetwork);
            
            if ($hashDiff->compare($quotient) < 0) {
                return 2;
            }
        }
        
        $diffNum = new Math_BigInteger($difficulty, 16);
        
        
        list ($quotient, $remainder) = $pow2_256->divide($diffNum);
        
        if ($hashDiff->compare($quotient) < 0) {
            return 1;
        }
        
        return false;
    }

    public function hashimotoLight($blockNumber, $cache, $headerHash, $nonce)
    {
        return $this->hashimoto($headerHash, $nonce, $this->getFullSize($blockNumber), $cache);
    }

    private function hashimoto($header, $nonce, $fullSize, $cache)
    {
        $n = floor($fullSize / self::$HASH_BYTES);
        $w = floor(self::$MIX_BYTES / self::$WORD_BYTES);
        
        $mixhashes = floor(self::$MIX_BYTES / self::$HASH_BYTES);
        
        $mix = '';
        
        $s = $this->sha3_512($header . $this->letterenbian($nonce));
        
        for ($k = 0; $k < $mixhashes; $k ++) {
            $mix .= $s;
        }
        
        for ($i = 0; $i < self::$ACCESSES; $i ++) {
            
            $p = $this->fnv($i ^ $this->getNum($s, 0), $this->getNum($mix, $i % $w));
            
            $p = ($p % floor($n / $mixhashes)) * $mixhashes;
            
            $newdata = '';
            
            for ($j = 0; $j < $mixhashes; $j ++) {
                $newdata .= $this->calcDatasetItem($cache, $p + $j);
            }
            
            // mix has 128bit ~ 32 int
            $newmix = '';
            
            for ($k = 0; $k < 32; $k ++) {
                $newmix .= $this->setNum($this->fnv($this->getNum($mix, $k), $this->getNum($newdata, $k)));
            }
            
            $mix = $newmix;
        }
        
        $cmix = '';
        
        for ($i = 0; $i < 32; $i += 4) {
            
            $cmix .= $this->setNum($this->fnv($this->fnv($this->fnv($this->getNum($mix, $i), $this->getNum($mix, $i + 1)), $this->getNum($mix, $i + 2)), $this->getNum($mix, $i + 3)));
        }
        
        return array(
            'digest' => $cmix,
            'result' => $this->sha3_256($s . $cmix)
        );
    }

    private function calcDatasetItem($cache, $i)
    {
        // the length of hash string is 64 byte.
        $n = strlen($cache) / 64;
        
        $r = floor(self::$HASH_BYTES / self::$WORD_BYTES);
        
        $mix = substr($cache, ($i % $n) * 64, 64);
        
        $mix = $this->setNum($this->getNum($mix, 0) ^ $i) . substr($mix, 4);
        
        $mix = $this->sha3_512($mix);
        
        for ($j = 0; $j < self::$DATASET_PARENTS; $j ++) {
            
            $cacheIndex = $this->fnv($i ^ $j, $this->getNum($mix, $j % $r));
            
            $currentCache = substr($cache, ($cacheIndex % $n) * 64, 64);
            
            $newmix = '';
            
            //
            for ($k = 0; $k < 16; $k ++) {
                
                $newmix .= $this->setNum($this->fnv($this->getNum($mix, $k), $this->getNum($currentCache, $k)));
            }
            
            $mix = $newmix;
        }
        
        return $this->sha3_512($mix);
    }

    public function getCache($blockNumber)
    {
        $seedLen = count($this->cacheSeeds);
        // get epoch
        $epochId = floor($blockNumber / self::$EPOCH_LENGTH);
        
        for ($i = $seedLen; $i <= $epochId; $i ++) {
            $this->cacheSeeds[] = $this->sha3_256($this->cacheSeeds[$i - 1]);
        }
        
        $seed = $this->cacheSeeds[$epochId];
        
        if (isset($this->cacheBySeed[$seed])) {
            
            $this->cacheBySeed[$seed]['fetchtime'] = time();
            
            return $this->cacheBySeed[$seed]['val'];
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

    private function makeCache($blockNumber)
    {
        $seedLen = count($this->cacheSeeds);
        // get epoch
        $epochId = floor($blockNumber / self::$EPOCH_LENGTH);
        
        for ($i = $seedLen; $i <= $epochId; $i ++) {
            
            $this->cacheSeeds[] = $this->sha3_256($this->cacheSeeds[$i - 1]);
        }
        
        $seed = $this->cacheSeeds[$epochId];
        
        $n = floor($this->getCacheSize($blockNumber) / self::$HASH_BYTES);
        
        return $this->_getCache($seed, $n);
    }

    private function _getCache($seed, $n)
    {
        $o = '';
        
        $lastHex = $seed;
        
        for ($i = 0; $i < $n; $i ++) {
            
            $tempSeedHash = $this->sha3_512($lastHex);
            
            $o .= $tempSeedHash;
            
            $lastHex = $tempSeedHash;
        }
        
        for ($i = 0; $i < self::$CACHE_ROUNDS; $i ++) {
            
            for ($j = 0; $j < $n; $j ++) {
                
                $tempKey = $this->getNum(substr($o, $j * 64, 64), 0) % $n;
                
                $fixKey = ($j + $n - 1) % $n;
                
                $newoHash = '';
                
                for ($k = 0; $k < 64; $k ++) {
                    $newoHash .= substr($o, $fixKey * 64 + $k, 1) ^ substr($o, $tempKey * 64 + $k, 1);
                }
                
                $newoHash = $this->sha3_512($newoHash);
                
                for ($k = 0; $k < 64; $k ++) {
                    $o[$j * 64 + $k] = $newoHash[$k];
                }
            }
        }
        
        return $o;
    }

    private function sha3_512($x)
    {
        return $this->hashWords($x, 512);
    }

    private function sha3_256($x)
    {
        return $this->hashWords($x, 256);
    }

    private function hashWords($content, $bit = 512)
    {
        if (is_array($content)) {
            
            $content = implode('', $content);
        }
        
        $y = keccak_hash($content, $bit);
        
        return $y;
    }

    private function unpackUint32($hex)
    {
        $d = ord($hex[3]);
        
        $d |= ord($hex[2]) << 8;
        
        $d |= ord($hex[1]) << 16;
        
        $d |= ord($hex[0]) << 24;
        
        return $d;
    }

    private function getCacheSize($blockNumber)
    {
        $epochId = floor($blockNumber / self::$EPOCH_LENGTH);
        
        $sz = self::$CACHE_BYTES_INIT + (self::$CACHE_BYTES_GROWTH * $epochId);
        $sz -= self::$HASH_BYTES;
        
        while (! $this->isPrimeNum(floor($sz / self::$HASH_BYTES))) {
            $sz -= 2 * self::$HASH_BYTES;
        }
        
        return $sz;
    }

    private function getNum($buf, $n)
    {
        return $this->unpackUint32($this->letterenbian(substr($buf, $n * 4, 4)));
    }

    private function setNum($n)
    {
        return $this->letterenbian(pack('N', $n));
    }

    private function letterenbian($n)
    {
        $return = '';
        $len = strlen($n);
        
        for ($i = 0; $i < $len; $i += 1) {
            $return = substr($n, $i, 1) . $return;
        }
        return $return;
    }

    private function getFullSize($blockNumber)
    {
        $epochId = floor($blockNumber / self::$EPOCH_LENGTH);
        
        $sz = self::$DATASET_BYTES_INIT + (self::$DATASET_BYTES_GROWTH * $epochId);
        
        $sz -= self::$MIX_BYTES;
        
        while (! $this->isPrimeNum(floor($sz / self::$MIX_BYTES))) {
            $sz -= 2 * self::$MIX_BYTES;
        }
        
        return $sz;
    }

    private function isPrimeNum($x)
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

    private function fnv($v1, $v2)
    {
        return (($v1 * self::$FNV_PRIME) ^ $v2) % 4294967296;
    }
}