<?php
include sha3.php;




class ethash{
    
    private $cacheSeeds=array();
    private $cacheBySeed=array();
    private $maxItems=10;
    
    static $WORD_BYTES = 4;                    # bytes in word
    static $DATASET_BYTES_INIT = 1073741824;        # bytes in dataset at genesis
    static $DATASET_BYTES_GROWTH = 8388608;      # growth per epoch (~7 GB per year)
    static $CACHE_BYTES_INIT = 16777216;          # Size of the dataset relative to the cache
    static $CACHE_BYTES_GROWTH = 131072;        # Size of the dataset relative to the cache
    static $EPOCH_LENGTH = 30000;              # blocks per epoch
    static $MIX_BYTES = 128;                   # width of mix
    static $HASH_BYTES = 64;                  # hash length in bytes
    static $DATASET_PARENTS = 256;             # number of parents of each dataset element
    static $CACHE_ROUNDS = 3;                  # number of rounds in cache production
    static $ACCESSES = 64;                     # number of accesses in hashimoto loop
    
    
    static $FNV_PRIME = 0x01000193;
    
    
    public  function __construct(){
        $this->cacheSeeds[]=pack('H*','0000000000000000000000000000000000000000000000000000000000000000');
    }
    
    /**
     * Check if the proof-of-work of the block is valid.
     * 
     * @param  $pendingBlockNumber
     * @param  $hashNoNonce
     * @param  $mixDigest
     * @param  $nonce
     * @param  $difficulty
     * 
     * @return bool true or false
     */
    public function verify($pendingBlockNumber,$hashNoNonce,$mixDigest,$nonce,$difficulty){
        
        if (strlen($mixDigest)!==32 || strlen($hashNoNonce)!==32 || strlen($nonce)!==8){
            return false;
        }
        
        
        $dagCache=$this->getCache($pendingBlockNumber);
        
        
        
        
    }
    
    private function getCache($blockNumber){
        
        $seedLen=count($this->cacheSeeds);
        
        for ($i=$seedLen;$i<=$blockNumber;$i++){
            
            $sponge = SHA3::init (SHA3::SHA3_256);
            
            
            $salt=$this->Hex2String($this->cacheSeeds[$i-1]);
            
            $sponge->absorb ($salt);
            
            $this->cacheSeeds[]=pack('H*',$sponge->squeeze ());
        }
        
        $seed=$this->cacheSeeds[$blockNumber];
        
        if (isset($this->cacheBySeed[$seed])){
         
            //@todo 移除最近访问的数据
            return $this->cacheBySeed[$seed];
            
        }
        
        
        
        
        
//         seed = cache_seeds[block_number // EPOCH_LENGTH]
//             if seed in cache_by_seed:
//             c = cache_by_seed.pop(seed)  # pop and append at end
//             cache_by_seed[seed] = c
//             return c
//             c = mkcache(block_number)
//             cache_by_seed[seed] = c
//             if len(cache_by_seed) > cache_by_seed.max_items:
//             cache_by_seed.pop(cache_by_seed.keys()[0])  # remove last recently accessed
//             return c
    }
    
    private function makeCache($blockNumber){
        
        
        
    }
    
    private function getCacheSize($blockNumber){
        $sz = self::$CACHE_BYTES_INIT + self::CACHE_BYTES_GROWTH * ($blockNumber);
        $sz -= self::HASH_BYTES
            
            while not isprime($sz):
                $sz -= 2 * self::HASH_BYTES
                return $sz
        
    }
    private function isprime($x){
        
    }
    
    function String2Hex($string){
        $hex='';
        for ($i=0; $i < strlen($string); $i++){
            $hex .= dechex(ord($string[$i]));
        }
        return $hex;
    }
    
    
    function Hex2String($hex){
        $string='';
        for ($i=0; $i < strlen($hex)-1; $i+=2){
            $string .= chr(hexdec($hex[$i].$hex[$i+1]));
        }
        return $string;
    }
    
}