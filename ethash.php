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
        //get epoch
        $epochId=floor($blockNumber/self::$EPOCH_LENGTH);
        
        for ($i=$seedLen;$i<=$epochId;$i++){
            
            $sponge = SHA3::init (SHA3::SHA3_256);
            
            
            $salt=$this->hex2String($this->cacheSeeds[$i-1]);
            
            $sponge->absorb ($salt);
            
            $this->cacheSeeds[]=pack('H*',$sponge->squeeze ());
        }
        
        $seed=$this->cacheSeeds[$epochId];
        
        if (isset($this->cacheBySeed[$seed])){

            $this->cacheBySeed[$seed]['fetchtime']=time();
         

            return $this->cacheBySeed[$seed];
            
        }

        $c=$this->makeCache($blockNumber);
        
        $this->cacheBySeed[$seed]=array(
            'val'=>$c,
            'fetchtime'=>time()
        );

        //remove last recently accessed
        if (count($this->cacheBySeed)>$this->maxItems){
            $mintime=time();
            $seedkey=false;

            foreach ($this->cacheBySeed as $key=>$val){
                if ($val['fetchtime']<$mintime){
                    $mintime=$val['fetchtime'];
                    $seedkey=$key;
                }
            }

            if (false!==$seedkey){
                unset($this->cacheBySeed[$seedkey]);
            }

        }
        
        return $c;
    }
    
    private function makeCache($blockNumber){

        $seedLen=count($this->cacheSeeds);
        //get epoch
        $epochId=floor($blockNumber/self::$EPOCH_LENGTH);


        for ($i=$seedLen;$i<=$epochId;$i++){

            $sponge = SHA3::init (SHA3::SHA3_256);


            $salt=$this->hex2String($this->cacheSeeds[$i-1]);

            $sponge->absorb ($salt);

            $this->cacheSeeds[]=pack('H*',$sponge->squeeze ());
        }

        $seed=$this->cacheSeeds[$epochId];

        $n=floor($this->getCacheSize($blockNumber)/self::$HASH_BYTES);

        return $this->_getCache($seed,$n);
    }

    private function _getCache($seed,$n){
        $o=array();
        $o[]=$this->sha3_512($seed);


    }

    private function sha3_512(){

    }
    
    private function getCacheSize($blockNumber){
        $sz = self::$CACHE_BYTES_INIT + (self::CACHE_BYTES_GROWTH * floor($blockNumber/self::$EPOCH_LENGTH));
        $sz -= self::HASH_BYTES;

        while (!$this->isPrimeNum(floor($sz/self::$HASH_BYTES))){
            $sz-=2*self::$HASH_BYTES;
        }

        return $sz;
    }

    private function isPrimeNum($x){
            $max=floor(sqrt($x));

            if($max>2){
                for ($i=2;$i<$max;$i++){
                    if (!($x%$i)){
                        return false;
                    }
                }
            }
            return true;
    }
    
    private function string2Hex($string){
        $hex='';
        for ($i=0; $i < strlen($string); $i++){
            $hex .= dechex(ord($string[$i]));
        }
        return $hex;
    }
    
    
    private function hex2String($hex){
        $string='';
        for ($i=0; $i < strlen($hex)-1; $i+=2){
            $string .= chr(hexdec($hex[$i].$hex[$i+1]));
        }
        return $string;
    }
    
}