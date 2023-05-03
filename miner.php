<?php

namespace PHPcoin\Miner;

error_reporting(0);

$type = trim($argv[1]);
$node = trim($argv[2]);
$address = trim($argv[3]);

new Miner($type, $node, $address);

/**
 * Class Miner
 */
class Miner
{
    /**
     * The current version of the miner.
     */
    public const VERSION = 'v0.1';
    /**
     * The mode name for pool mining.
     */
    public const MODE_SOLO = 'pool';
    /**
     * The response status for OK.
     */
    public const NODE_STATUS_OK = 'ok';
	
    /**
     * Miner constructor.
     *
     * @param $type
     * @param $node
     * @param $public_key
     * @param $private_key
     */
    public function __construct($type, $node, $address)
    {
        $this->outputHeader();

        $this->checkDependencies();

        if (empty($type) || empty($address) || empty($node)) {
            echo "Usage: For Solo mining: ./miner pool http://sg-mine.phpcoin.me <address> \n\n";
            exit;
        }
		if ($type == "pool") {
            
        }else{
			echo "Usage: For pool mining: ./miner pool http://sg-mine.phpcoin.me <address> \n\n";
            exit;
		}
		if($node != "http://sg-mine.phpcoin.me"){
			die("ERROR: node Only support http://sg-mine.phpcoin.me dont use other node");
		}

        $worker = uniqid();

        $this->prepare($address, $node, $type, $worker);
        $res = $this->update();
        if (!$res) {
            die("ERROR: Could not get mining info from the node");
        }

        $this->run();
    }

    /**
     * @param string $publicKey
     * @param string $privateKey
     * @param string $node
     * @param string $type
     * @param string $worker
     */
    public function prepare(string $address, string $node, string $type, string $worker)
    {
        $this->address = $address;
        $this->node = $node;
        $this->type = $type;
        $this->worker = $worker;
        $this->counter = 0;
        $this->submit = 0;
        $this->confirm = 0;
		$this->reject = 0;
        $this->gets = 0;
		$this->hit = 0;
		$this->target = 0;
    }

    /**
     * @return bool
     */
	public function gmp_hexdec($n)
	{
		$gmp = gmp_init(0);
		$mult = gmp_init(1);
		for ($i=strlen($n)-1;$i>=0;$i--,$mult=gmp_mul($mult, 16)) {
			$gmp = gmp_add($gmp, gmp_mul($mult, hexdec($n[$i])));
		}
		return $gmp;
	}
    public function update(): bool
    {
        $this->lastUpdate = time();

        echo "--> Updating mining info\n";

        $extra = "";
		
        $res = file_get_contents($this->node."/mine.php?q=info".$extra);

        $info = json_decode($res, true);
        if ($info['status'] != self::NODE_STATUS_OK) {
            return false;
        }
        $data				= $info['data'];
		$this->coin			= $info['coin'];
		$this->version		= $info['version'];
        $this->block		= $data['block'];
        $this->difficulty	= $data['difficulty'];
		$this->height		= $data['height']+1;
		$this->datex		= $data['date'];
		$this->add			= $data['add'];
		$this->timex		= $data['time'];
		$this->reward		= $data['reward'];
		$this->datax		= $data['data'];
		if(!$this->add){
			die("ERROR: Please Use Pool sg-mine.phpcoin.me");
		}
        return true;
    }
    private function submit(array $opts): bool
    {
        echo "--> Submitting nonce....\n";
		
        $context = stream_context_create($opts);

        $res = file_get_contents($this->node."/mine.php?q=submitHash", false, $context);
        $data = json_decode($res, true);

        if ($data['status'] == self::NODE_STATUS_OK) {
			if($data['data'] == "find"){
				echo "--> Block confirmed.\n";
				$this->gets++;
			}else{
				$this->confirm++;
				echo "--> Nonce confirmed.\n";
			}
            return true;
        } else {
			$this->reject++;
            echo "--> Nonce confirmed.\n";
            return false;
        }
    }

    /**
     * Run the miner.
     */
    public function run()
    {
        $this->allTime = microtime(true);
        $this->beginTime = time();
        $it = 0;
        $this->counter = 0;
        $start = microtime(true);
		$maxhit = 0;
		$maxtarget = 0;
        while (1) {
            $this->counter++;

            if (time() - $this->lastUpdate > 5) {
				if($elapsed <= 9){
					echo "--> Elapsed: 00".$elapsed."	".
						"Hit: ".substr($hit,0,4)."	".
						"Find Block: ".$this->gets."	 ".
						"Submit: ".$this->confirm."	".
						"Reject: ".$this->reject."\n";
					$this->update();
				}else{
					if($elapsed <= 99){
						echo "--> Elapsed: 0".$elapsed."	".
							"Hit: ".substr($hit,0,4)."	".
							"Find Block: ".$this->gets."	 ".
							"Submit: ".$this->confirm."	".
							"Reject: ".$this->reject."\n";
						$this->update();
					}else{
						echo "--> Elapsed: 00".$elapsed."	".
							"Hit: ".substr($hit,0,4)."	".
							"Find Block: ".$this->gets."	 ".
							"Submit: ".$this->confirm."	".
							"Reject: ".$this->reject."\n";
						$this->update();
					}
				}
            }
			$max			= $this->gmp_hexdec('FFFFFFFF');
			$now			= round(time() / 1000);
			$offset			= $this->timex - $now;
			$elapsed		= bcsub(bcadd($now , $offset) , $this->datex);
			$argonBase		= $this->datex."-".$elapsed;
			$new_block_date	= $this->datex + $elapsed;
			if($elapsed <= 0){
				sleep(1);
				echo "--> Elapsed: 00".$elapsed."	".
                    "Hit: ".substr($hit,0,4)."	".
                    "Find Block: ".$this->gets."	 ".
					"Submit: ".$this->confirm."	".
					"Reject: ".$this->reject."\n";
				$this->update();
			}else{
				$argon = password_hash(
				$argonBase,
				PASSWORD_ARGON2I,
				['memory_cost' => 2048, "time_cost" => 2, "threads" => 1, 'type'=> 'argon2.argon2i', 'hashLength'=> 32]
				);
				$nonceBase = $this->add."-".$this->datex."-".$elapsed."-".$argon;
				$calcNonce = hash("sha256", $nonceBase);
				$hitBase = $this->add."-".$calcNonce."-".$this->height."-".$this->difficulty;
				$hash1 = hash("sha256", $hitBase);
				$hash2 = hash("sha256", $hash1);
				$hashPart = substr($hash2, 0, 8);
				$hitValue = $this->gmp_hexdec($hashPart);
				$hit = gmp_div(gmp_mul($max, 1000) , $hitValue);
				if($hit > $maxhit){
					$maxhit = $hit;
				}
				$target = gmp_div(gmp_mul($this->difficulty, 60), $elapsed);
				if($target > $maxtarget){
					$maxtarget = $target;
				}
				if (sizeof($this->datax) == 0) {
					$gs = array();
					$datz = json_encode($gs);
				}else{
					$datz = json_encode($data['data']);
				}
				if($hit > $target){
					$mininfo = array(
						'miner'	=>	'phpcoin.me',
					);
					$postData = http_build_query(
						[
							'argon'			=> $argon,
							'nonce'			=> $calcNonce,
							'height'		=> $this->height,
							'difficulty'	=> $this->difficulty,
							'address'		=> $this->address,
							'add'			=> $this->add,
							'date'			=> $new_block_date,
							'data'			=> $datz,
							'elapsed'		=> $elapsed,
							'minerInfo'		=> $mininfo,
						]
					);

					$opts = [
						'http' =>
						[
							'method'  => 'POST',
							'header'  => 'Content-type: application/x-www-form-urlencoded',
							'content' => $postData,
						],
					];
					$this->submit($opts);
				}
				
			}
            $it++;
            if ($it == 100) {
                $it = 0;
                $end = microtime(true);

                $this->speed = 10 / ($end - $start);
                $this->avgSpeed = $this->counter / ($end - $this->allTime);
                $start = $end;
            }
        }
    }
    public function checkDependencies()
    {
        if (!extension_loaded("gmp")) {
            die("The GMP PHP extension is missing.");
        }

        if (!extension_loaded("openssl")) {
            die("The OpenSSL PHP extension is missing.");
        }

        if (floatval(phpversion()) < 7.2) {
            die("The minimum PHP version required is 7.2.");
        }

        if (!defined("PASSWORD_ARGON2I")) {
            die("The PHP version is not compiled with argon2i support.");
        }
    }
    public function outputHeader()
    {
        echo "######################\n";
        echo "#   PHPcoin Miner    #\n";
        echo "#   phpcoin.me       #\n";
        echo "######################\n\n";
    }
}