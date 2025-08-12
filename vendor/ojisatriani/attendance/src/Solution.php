<?php

namespace OjiSatriani\Attendance;

class Solution
{
    protected $ipAddress;
    protected $port;
    protected $key;
    protected $ping;
    protected $dateStart;
    protected $dateEnd;

    public function __construct(array $config = []) {
        $this->ipAddress    = $config['ip_address'] ?? '';
        $this->port         = $config['port'] ?? '';
        $this->key          = $config['key'] ?? 0;
        $this->dateStart    = $config['date_start'] ?? NULL;
        $this->dateEnd      = $config['date_end'] ?? date('Y-m-d H:i:s');
        $this->pin          = $config['pin'] ?? 'All';
    }

    public static function init($config = NULL)
    {
        return new static($config);
    }

    public function parse($data,$tag_opening,$tag_closing){
        $data               = " " . $data;
        $result             = "";
        $start              = strpos($data, $tag_opening);
        if($start != "")
        {
            $end            = strpos(strstr($data, $tag_opening), $tag_closing);
            if($end != "")
            {
                $result     = substr($data, $start+strlen($tag_opening), $end-strlen($tag_opening));
            }
        }
        return $result;
    }

    public function connect()
    {
        return @fsockopen($this->ipAddress, $this->port, $errno, $errstr, 1);
    }

    public function getAttLog()
    {
        try {
            $connect            = $this->connect();
            if (is_resource($connect))
            {
                $soap_request   = "<GetAttLog><ArgComKey xsi:type=\"xsd:integer\">".$this->key."</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">".$this->pin."</PIN></Arg></GetAttLog>";
                $newLine        = "\r\n";
                fputs($connect, "POST /iWsService HTTP/1.0". $newLine);
                fputs($connect, "Content-Type: text/xml". $newLine);
                fputs($connect, "Content-Length: ". strlen($soap_request) . $newLine . $newLine);
                fputs($connect, $soap_request . $newLine);
                $buffer         = "";
                while($Response = fgets($connect, 1024)){
                        $buffer = $buffer.$Response;
                }
                $log_response   = $this->parse($buffer, "<GetAttLogResponse>", "</GetAttLogResponse>");
                $log_response   = str_replace("\r\n", "\n", $log_response);
                $log            = explode("\n", $log_response);
                krsort($log);
                return $log;
            } else {
                return [];
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function response()
    {
        $data           = [];
        foreach($this->getAttLog() as $log){
            $row        = $this->parse($log,"<Row>","</Row>");
            $pin        = (int)$this->parse($row,"<PIN>","</PIN>");
            $datetime   = $this->parse($row,"<DateTime>","</DateTime>");
            if ($pin)
            {
                $data[] = [
                                'pin'       => $pin,
                                'datetime'  => $datetime,
                ];
            }
        }
        return $data;
    }
}
