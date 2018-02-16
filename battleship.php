<?php
/**
 * Created by PhpStorm.
 * User: stantonca
 * Date: 10/6/16
 * Time: 8:14 PM
 */


require_once('rollingcurl.php');

class Battleship {

    var $BASE_URL = 'https://anypoint.mulesoft.com/apiplatform/proxy/http://battleships-apidays.au.cloudhub.io/api/v1/';
    var $BOARD_PATH = 'battlefield';
    var $ME_PATH = 'users/me';
    var $FIRE_PATH = 'fire';
    var $GET = 'GET';
    var $PUT = 'PUT';
    var $POST = 'POST';
    var $board = null;

    var $UNKNOWN = 0;
    var $HIT = 1;
    var $MISS = 2;

    var $last = 0;

    var $craigStantonCreds = array( 'client_id'=>'',//CraigStanton
                            'client_secret'=>'');


    var $bruteForceCreds = array( 'client_id'=>'',//brute force test creds
                                'client_secret'=>'');//CSTEST


    var $smartTargetCreds =  array( 'client_id'=>'',//smart target
                                'client_secret'=>'');//UberHiker

    var $creds = array();

    var $RCX = null;

    function __construct()
    {
        $this->board = null;
        $this->creds = $this->smartTargetCreds;

        $this->RCX = new RollingCurlX(4);
        $this->RCX->setTimeout(2000); //in milliseconds

    }

    function callAPI($url, $method, $credentials, $data)
    {

        if ($credentials == null)
        {
            $credentials = $this->creds;
        }


        $curl = curl_init();

        switch ($method)
        {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);

                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_PUT, 1);
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }

        $credentials['Content-Type'] = 'application/json';

        $headers = array();
        foreach($credentials as $key=>$value)
        {
            $headers[] = $key.": ".$value;
        }



        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);


        curl_setopt($curl,CURLOPT_TIMEOUT,10);

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);
        //echo date("c")."\n";

        $milliseconds = round(microtime(true) * 1000);
        $diff = $milliseconds - $this->last;

        $this->last = $milliseconds;
        //echo "time since last = ".$diff."\n";


        curl_close($curl);

        return $result;


    }

    function hasBoardBeenReset()
    {
        $currentBoardScore = $this->boardScore($this->board);

        $this->loadBoard();

        $newBoardScore = $this->boardScore($this->board);


        return ($currentBoardScore > $newBoardScore);

    }

    function boardScore($board)
    {
        $total = 0;
        foreach($board as $y => $row)
        {
            foreach($row as $x => $cell)
            {
                $total += $cell;
            }
        }
        return $total;
    }

    function loadBoard(){
        $result = $this->callAPI($this->BASE_URL.$this->BOARD_PATH, $this->GET, $this->creds, null);

        $data = json_decode($result, true);

        if ($data['state'] != null)
        {
            $this->board = $data['state'];
            $this->displayBoard($this->board);

        } else {
            echo $result;
        }

        return $data;
    }

    function displayBoard($board = null)
    {
        if ($board == null)
        {
            $this->ensureBoard();
            $board = $this->board;
        }

        foreach($board as $y => $row)
        {
            foreach($row as $x => $cell)
            {
                echo $cell;
            }
            echo "\n";
        }
    }

    function fire($x, $y, $creds = null){
        $hit = false;
        $data = new stdClass();
        $data->x = $x;
        $data->y = $y;
        $payload = json_encode($data);
        $result = $this->callAPI($this->BASE_URL.$this->FIRE_PATH, $this->POST, $creds, $payload);

        //echo $result;


        $data = json_decode($result, true);

        if ($data['message'] != null)
        {
            if ($data['message'] === 'Miss!')
            {
                $this->recordHit($x,$y,$this->MISS);
                echo "BOO!";
            } else if($data['message'] === 'Hit!' || $data['message'] === 'Sunk!'){
                $this->recordHit($x,$y,$this->HIT);
                echo "YAY! ($x,$y)";
                $hit = true;
            }
        } else {
            //echo $result;
        }
        echo "$result\n";
        $this->displayBoard();
        return $hit;
    }

    function hunt()
    {
        $board = $this->loadBoard();

        $width = $board['width'];
        $depth = $board['depth'];

        for($y = 0; $y<$depth; $y++)
        {
            for($x = (($y+1)%2); $x<$width; $x+=2)
            {
               /* $result = $this->fire($x,$y);
                $this->displayBoard($this->board);
                if ($result)
                {
                    $this->target($x,$y);
                }
               */
                if ($this->board[$y][$x] == $this->UNKNOWN) {
                    $this->fire($x, $y, $this->bruteForceCreds);
                }
                //$this->fireAndTarget($x,$y, $this->bruteForceCreds);
            }
        }
    }

    function clearBoard()
    {
        $board = $this->loadBoard();
        $width = $board['width'];
        $depth = $board['depth'];
        $clearCounter = 0;

        for($y = 0; $y<$depth; $y++)
        {
            for($x = 0; $x<$width; $x++)
            {
                if ($this->board[$y][$x] == $this->UNKNOWN) {
                    $this->fire($x, $y, $this->bruteForceCreds);
                    $clearCounter++;
                    if ($clearCounter % 10 == 0 && $this->hasBoardBeenReset())
                    {
                        echo "*******************New board******************";
                        return;
                    }
                }
            }
        }
    }

    function checkStats($creds = null)
    {
        $result = $this->callAPI($this->BASE_URL.$this->ME_PATH, $this->GET, $creds, null);
        echo $result ,"\n";



        $data = json_decode($result, true);
        return $data;
    }

    function fireAndTarget($x,$y, $creds)
    {
        if($this->board[$y][$x] == $this->UNKNOWN && $this->fire($x,$y, $creds))
        {
            $this->target($x,$y, $creds);
        }
    }

    function target($x,$y, $creds)
    {
        //Try north
        if ($y > 0  && $this->board[$y-1][$x] == $this->UNKNOWN)
        {
            $this->fireAndTarget($x, $y-1, $creds);
        }
        //Try east
        if ($x < (count($this->board[$y])-2) && $this->board[$y][$x+1] == $this->UNKNOWN)
        {
            $this->fireAndTarget($x+1, $y, $creds);
        }
        //Try south
        if ($y < (count($this->board)-2) && $this->board[$y+1][$x] == $this->UNKNOWN)
        {
            $this->fireAndTarget($x, $y+1, $creds);
        }
        //Try west
        if ($x > 0  && $this->board[$y][$x-1] == $this->UNKNOWN)
        {
            $this->fireAndTarget($x-1, $y, $creds);
        }
    }

    function targetKnownHits()
    {
        $board = $this->loadBoard();
        $width = $board['width'];
        $depth = $board['depth'];

        for($y = 0; $y<$depth; $y++)
        {
            for($x = 0; $x<$width; $x++)
            {
                if ($this->board[$y][$x] == $this->HIT) {
                    $this->target($x, $y, $this->smartTargetCreds);
                }
            }
        }
    }

    function recordHit($x,$y,$state)
    {
        $this->ensureBoard();
        $this->board[$y][$x] = $state;
    }

    function ensureBoard()
    {
        if ($this->board == null)
        {
            $this->loadBoard();
        }
    }

    function checkState($x,$y)
    {
        $this->ensureBoard();
    }

    function threadedSearch()
    {

        $board = $this->loadBoard();

        $width = $board['width'];
        $depth = $board['depth'];

        for($y = 0; $y<$depth; $y++)
        {
            for($x = (($y+1)%2); $x<$width; $x+=2)
            {

                if($this->board[$y][$x] == $this->UNKNOWN)
                {
                    $this->threadedFire($x,$y, $this->bruteForceCreds);
                }
            }
            $this->RCX->execute();
            $this->loadBoard();
        }
    }

    function threadedClear()
    {

        $board = $this->loadBoard();

        $width = $board['width'];
        $depth = $board['depth'];

        $clearCounter = 0;

        for($y = 0; $y<$depth; $y++)
        {
            //$this->loadBoard();
            for($x = 0; $x<$width; $x+=1)
            {

                if($this->board[$y][$x] == $this->UNKNOWN)
                {
                    $this->threadedFire($x,$y, $this->bruteForceCreds);
                    $clearCounter++;
                }
            }
            $this->RCX->execute();
            if ($clearCounter == 20) {
                $clearCounter = 0;
                echo "Have reached 20, checking\n";
                if ($this->hasBoardBeenReset()) {
                    echo "*******************New board******************";
                    return;
                }
            }
        }
    }

    function fire_callback($response, $url, $request_info, $user_data, $time) {
        $time; //how long the request took in milliseconds (float)
        $request_info; //returned by curl_getinfo($ch)

        echo "threaded response = ".$response;

        $data = json_decode($response, true);

        if ($data['message'] != null)
        {
            $x = $data['x'];
            $y = $data['y'];
            if ($data['message'] === 'Miss!')
            {
                //$this->recordHit($x,$y,$this->MISS);
                echo "BOO!";
            } else if($data['message'] === 'Hit!' || $data['message'] === 'Sunk!'){
                //$this->recordHit($x,$y,$this->HIT);
                echo "YAY! ($x,$y)";
                $hit = true;
            }
        } else {
            //echo $result;
        }
    }

    function threadedFire($x, $y, $creds = null){
          $hit = false;
          $data = new stdClass();
          $data->x = $x;
          $data->y = $y;
          $payload = json_encode($data);
          //$result =

         echo "Stacking ($x,$y)\n";
          $this->addThreadedAPICall($this->BASE_URL.$this->FIRE_PATH, $creds, $payload, 'Battleship::fire_callback' );

          //echo $result;



      }
    function addThreadedAPICall($url, $creds, $post_data, $callback)
    {

        if ($creds == null)
        {
            $creds = $this->creds;
        }
        $creds['Content-Type'] = 'application/json';

        $headers = array();
        foreach($creds as $key=>$value)
        {
            $headers[] = $key.": ".$value;
        }


        $this->RCX->setOptions([CURLOPT_RETURNTRANSFER => 1]);

        $this->RCX->addRequest($url, $post_data, $callback, null, null, $headers);
    }


    function fullplay()
    {



        while(true)
        {
            echo "Hunting";
            $this->hunt();

            echo "Targeting";
            $this->targetKnownHits();

            echo "clearing";
            //$this->threadedClear();
            $this->clearBoard();

        }
    }
}

$bs = new Battleship();
//$bs->hunt();

//$bs->fire(6,8);



//$arg = $argv[0];
foreach ($argv as $i=>$arg )
{
    if ( $arg == "stats" )
    {
        if( count($argv) > 2) {
            if ($argv[2] == 'smart') {
                $bs->checkStats($bs->smartTargetCreds);
            } else if ($argv[2] == 'craig') {
                $bs->checkStats($bs->craigStantonCreds);
            } else if ($argv[2] == 'brute') {
                $bs->checkStats($bs->bruteForceCreds);
            } else if ($argv[2] == 'all') {

                while(true)
                {
                    echo "Smart target user : ";
                    $smartStats = $bs->checkStats($bs->smartTargetCreds);
                    echo "Craig Stanton user : ";
                    $craigStats = $bs->checkStats($bs->craigStantonCreds);
                    echo "Brute force user : ";
                    $bruteStats = $bs->checkStats($bs->bruteForceCreds);

                    $line = $smartStats['points'].','.$craigStats['points'].','.$bruteStats['points'].',';
                    $line .= $smartStats['total_sunk'].','.$craigStats['total_sunk'].','.$bruteStats['total_sunk'].',';
                    $line .= $smartStats['total_hits'].','.$craigStats['total_hits'].','.$bruteStats['total_hits'].',';
                    $line .= $smartStats['total_shots'].','.$craigStats['total_shots'].','.$bruteStats['total_shots'].',';
                    $line .= $smartStats['hit_miss_ratio'].','.$craigStats['hit_miss_ratio'].','.$bruteStats['hit_miss_ratio']."\n";



                    file_put_contents("output.csv", $line, FILE_APPEND);


                    sleep(60);
                    echo "\033[2A";
                    echo "\033[2A";
                    echo "\033[2A";
                }

            }
        }else {
            $bs->checkStats();
        }


    } else if ($arg == "hunt") {
        $bs->hunt();
    } else if ($arg == "print") {
        $bs->loadBoard();
    } else if ($arg == "target") {
        $bs->targetKnownHits();
    }else if ($arg == "clear") {
        $bs->clearBoard();
    }else if ($arg == "threaded") {
        $bs->threadedSearch();
    }else if ($arg == "play") {
        $bs->fullplay();
    }
}
