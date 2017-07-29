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
    public function verify($pendingBlockNumber, $hashNoNonce, $mixDigest, $nonce, $difficulty)
    {
        if (strlen($mixDigest) !== 32 || strlen($hashNoNonce) !== 32 ) {
            return false;
        }
        
        $dagCache = $this->getCache($pendingBlockNumber);
        
        $miningOutput = $this->hashimotoLight($pendingBlockNumber,$dagCache,$hashNoNonce,$nonce);
        
        var_dump(unpack('H*', $miningOutput['digest']));
        
        var_dump(unpack('H*', $miningOutput['result']));
        
    }

    public function hashimotoLight($blockNumber, $cache, $headerHash, $nonce)
    {
        echo $this->getFullSize($blockNumber);
        return $this->hashimoto($headerHash, $nonce, $this->getFullSize($blockNumber), $cache);
    }

    private function hashimoto($header, $nonce, $fullSize, $cache)
    {
        $n = floor($fullSize / self::$HASH_BYTES);
        $w = floor(self::$MIX_BYTES / self::$WORD_BYTES);
        
        $mixhashes = floor(self::$MIX_BYTES / self::$HASH_BYTES);
        
        $mix = array();
        
        $s = $this->sha3_512($header . $this->letterenbian($nonce));
        
        for ($k = 0; $k < $mixhashes; $k ++) {
            $mix=array_merge($mix,$s);
        }
        
        for ($i = 0; $i < self::$ACCESSES; $i ++) {

            $p = $this->fnv($i ^ $s[0], $mix[$i % $w]);
            
            $p = ($p % floor($n / $mixhashes)) * $mixhashes;
            
            $newdata = array();
            
            for ($j = 0; $j < $mixhashes; $j ++) {
                $newdata = array_merge($newdata,$this->calcDatasetItem($cache, $p + $j));
            }
            // mix has 128bit ~ 32 int
            $newmix=array();

            for ($k=0;$k<32;$k++){
                $newmix[]=$this->fnv($mix[$k], $newdata[$k]);
            }
            
            $mix=$newmix;
        }
        
        $cmix=array();
        
        for ($i=0;$i<32;$i+=4){
            
            $cmix[]=$this->fnv($this->fnv($this->fnv($mix[$i], $mix[$i+1]), $mix[$i+2]), $mix[$i+3]);
            
        }
        
        return array(
            'digest'=>$this->serializeHash($cmix),
            'result'=>$this->serializeHash($this->sha3_256(array_merge($s,$cmix)))
        );
        
    }

    private function calcDatasetItem($cache, $i)
    {
        // 64 is length of hash string.
        $n = count($cache);
        
        $r = floor(self::$HASH_BYTES / self::$WORD_BYTES);
        
        $mix = $cache[$i%$n];
        
        $mix[0] ^=  $i;
        
        $mix = $this->sha3_512($mix);
        
        
        
        for ($j = 0; $j < self::$DATASET_PARENTS; $j ++) {
            
            $cacheIndex = $this->fnv($i ^ $j, $mix[$j % $r]);
            
            $currentCache=$cache[$cacheIndex%$n];
            
            $newmix=array();
            
            for ($k=0;$k<16;$k++){
                
                $newmix[]=$this->fnv($mix[$k], $currentCache[$k]);
                
            }
            
            $mix=$this->sha3_512($newmix);
            
        }
        
        return $this->sha3_512($newmix);
    }

    public function getCache($blockNumber)
    {
        $seedLen = count($this->cacheSeeds);
        // get epoch
        $epochId = floor($blockNumber / self::$EPOCH_LENGTH);
        
        echo $epochId;
        
        for ($i = $seedLen; $i <= $epochId; $i ++) {
            $this->cacheSeeds[] = sha3($this->cacheSeeds[$i - 1], 256, true);
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
            
            $this->cacheSeeds[] = sha3($this->hex2String($this->cacheSeeds[$i - 1]), 256, true);
        }
        
        $seed = $this->cacheSeeds[$epochId];
        
        $n = floor($this->getCacheSize($blockNumber) / self::$HASH_BYTES);
        
        return $this->_getCache($seed, $n);
    }

    private function _getCache($seed, $n)
    {
        $o = array();
        
        $lastHex = $seed;
        
        for ($i = 0; $i < $n; $i ++) {
            
            $tempSeedHash = $this->sha3_512($lastHex);
            
            $o[] = $tempSeedHash;
            
            $lastHex = $tempSeedHash;
        }
        
        echo "\r\n o length:" . count($o)*64;
        
        for ($i = 0; $i < self::$CACHE_ROUNDS; $i ++) {
            
            for ($j = 0; $j < $n; $j ++) {

                $tempKey = $o[$j][0] % $n;
                
                $fixKey = ($j + $n - 1) % $n;

                
                $newoHash=array();
                
                for ($k=0;$k<16;$k++){
                    
                    
                    $newoHash[]=$o[$fixKey][$k]^$o[$tempKey][$k];
                    
                }

                $newoHash=$this->sha3_512($newoHash);
                
                $o[$j]=$newoHash;
            }
        }
        
        return $o;
    }

    private function sha3_512($x)
    {
        return $this->hashWords($x,512);
    }

    private function sha3_256($x)
    {
        return $this->hashWords($x,256);
    }

    private function hashWords($content,$bit=512){

        if (is_array($content)) {

            $content=$this->serializeHash($content);
        }

        $y=sha3($content, $bit, true);


        return $this->deserializeHash($y);
    }

    private function serializeHash($content){
        $newcontent='';

        //convert to hex
        foreach ($content as $item) {
            $hex = '';

            if ($item > 0) {

                $itemhex = dechex($item);

                $temp = str_pad($itemhex, strlen($itemhex) + (strlen($itemhex) % 2), '0', STR_PAD_LEFT);

                $hex = pack('H*', $this->letterenbian($temp));
            }

            $newcontent .= str_pad($hex, 4, 0x00);

        }

        return$newcontent;
    }

    private function deserializeHash($hash)
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

    private function letterenbian($n)
    {
        $return = '';
        for ($i = 0; $i < strlen($n); $i += 1) {
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

    private function string2Hex($string)
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i ++) {
            $hex .= dechex(ord($string[$i]));
        }
        return $hex;
    }

    private function hex2String($hex)
    {
        return $hex;
        $string = '';
        for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
            $string .= chr(hexdec($hex[$i] . $hex[$i + 1]));
        }
        return $string;
    }

    private function fnv($v1, $v2)
    {
        return (($v1 * self::$FNV_PRIME) ^ $v2) % 4294967296;
    }
}