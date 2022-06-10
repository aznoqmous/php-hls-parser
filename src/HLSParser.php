<?php

namespace App;

class HLSParser
{

    private $resolutions = [];

    private $inputFile;
    private $inputFileName;

    private $outputFile;
    private $outputDir;
    private $outputFileName;

    private $logFile;

    private $ffmpegBinary = "c:/laragon/bin/ffmpeg.exe";
    private $ffprobeBinary = "c:/laragon/bin/ffprobe.exe";

    private $arguments = [];

    /**
     * Private constructor: Use HLSParser::open($inputFile) instead
     * @param $inputFile
     * @throws \Exception
     */
    private function __construct($inputFile)
    {
        if (!is_file($inputFile)) throw  new \Exception("$inputFile is not a valid file");
        $this->inputFile = $inputFile;
        $this->inputFileName = basename($inputFile);
        $this->outputFileName = explode('.', $this->inputFileName)[0];
    }

    /**
     * Create an instance of HLSParser from input video
     * @param $inputFile
     * @return HLSParser
     * @throws \Exception
     */
    public static function open($inputFile)
    {
        return new self($inputFile);
    }

    /**
     * Generate sliced video + .m3u8 file for each $resolutions
     * Also generate a master.m3u8 file to handle different resolutions inside videojs
     * @param $outputDir
     * @param $outputFileName
     * @return string
     * @throws \Exception
     */
    public function save($outputDir, $outputFileName = null)
    {
        $this->outputDir = $outputDir;
        if ($outputFileName) $this->outputFileName = $outputFileName;
        //if (is_dir($this->outputDir)) rmdir($this->outputDir);
        if (!is_dir($this->outputDir)) mkdir($this->outputDir);

        foreach ($this->resolutions as $res => $resolution) {
            $this->export($resolution);
        }

        return $this->buildMasterFile();
    }

    public function generateThumbnail($outputFile, $timestamp = 0, $resolution=null)
    {
        if(!$resolution) $resolution = new Resolution(360, 200);
        $this->setArguments([
            "-ss" => date("H:i:s.u", $timestamp),
            "-frames:v" => 1,
            "-vf" => "scale=$resolution->width:$resolution->height"
        ]);
        $this->setOutputFile($outputFile);
        $this->exec();
    }

    public function generateThumbnails($outputDir, $outputFileName = null, $strFrameRate="1/5", $resolution=null)
    {
        if(!$resolution) $resolution = new Resolution(168, 94);
        $this->outputDir = $outputDir;
        if ($outputFileName) $this->outputFileName = $outputFileName;
        $this->setArguments([
            "-vf" => "\"fps=$strFrameRate, scale=$resolution->width:$resolution->height\""
        ]);
        $this->setOutputFile("$this->outputDir/$this->outputFileName-%04d.jpg");
        $this->exec();
        return array_map(function($file)use($outputDir){
            return "$outputDir/$file" ;
        }, array_values(array_filter(scandir($this->outputDir), function($file){
            return preg_match("/$this->outputFileName-\d*?\.jpg/", $file);
        })));
    }

    public function generateThumbnailsSprite($outputFile, $strFrameRate="1/5", $resolution=null){
        $images = $this->generateThumbnails(dirname($outputFile), "tmp", $strFrameRate);
        if(!$resolution) $resolution = new Resolution(168, 94);
        $imagesCount = count($images);
        $rows = ceil(sqrt($imagesCount));
        $targetImage = imagecreatetruecolor($resolution->width * $rows, $resolution->height * $rows);
        $i = 0;
        for($y=0; $y < $rows; $y++){
            for($x=0; $x < $rows; $x++){
                $image = imagecreatefromjpeg($images[$i]);
                imagecopy($targetImage, $image, $x * $resolution->width, $y * $resolution->height, 0, 0, $resolution->width, $resolution->height);
                $i++;
                if($i >= $imagesCount) break;
            }
            if($i >= $imagesCount) break;
        }
        imagejpeg($targetImage, $outputFile);
        foreach ($images as $image) unlink($image);
        return $this;
    }

    /**
     * Generate sliced video + .m3u8 file for given $resolution
     * @param Resolution $resolution
     * @throws \Exception
     */
    private function export(Resolution $resolution)
    {
        $this->setArguments([
            "-profile:v" => "baseline",
            "-level" => "3.0",
            "-start_number" => 0,
//            "-hls_time" => 10,
//            "-hls_init_time" => 10,
            "-hls_list_size" => 0,
            "-f" => "hls"
        ]);
        $this->setResolution($resolution);
        $this->setOutputFile("$this->outputDir/$this->outputFileName-$resolution->res.m3u8");
        $this->setLogFile("$this->outputDir/log-$resolution->res.txt");

        return $this->exec();
    }

    private function getCommand()
    {
        $command = "$this->ffmpegBinary -i $this->inputFile";
        foreach ($this->arguments as $key => $value) {
            $command .= " $key";
            if ($value !== null) $command .= " $value";
        }
        if ($this->outputFile) $command .= " $this->outputFile";
        if ($this->logFile) $command .= " 1>$this->logFile 2>&1"; // log SIGERR
        return $command;
    }

    private function exec()
    {
        $command = $this->getCommand();
        exec($command, $output, $return_code);
        if ($return_code) {
            array_splice($output, 0, 11);
            throw new \Exception(implode('<br>', $output));
        }
        return $this;
    }

    /**
     * Build the master.m3u8 file following $resolutions
     * @return string
     */
    private function buildMasterFile()
    {
        $masterFile = "$this->outputDir/$this->outputFileName-master.m3u8";
        if (is_file($masterFile)) unlink($masterFile);
        $file = fopen($masterFile, "w+");
        fputs($file, "#EXTM3U" . PHP_EOL);
        foreach ($this->resolutions as $res => $resolution) {
            fputs($file, "#EXT-X-STREAM-INF:BANDWIDTH=375000,RESOLUTION={$resolution->getLabel()}" . PHP_EOL);
            fputs($file, "$this->outputFileName-$resolution->res.m3u8" . PHP_EOL);
        }
        return $masterFile;
    }

    # ---------------------------------------------------------------------------------------------
    # FFMPEG Parameters
    # ---------------------------------------------------------------------------------------------

    private function setOutputFile($outputFile)
    {
        $this->outputFile = $outputFile;
    }

    private function setLogFile($logFile)
    {
        $this->logFile = $logFile;
    }

    private function setResolution(Resolution $resolution)
    {
        $this->setArgument("-s", $resolution->getLabel());
    }

    public function setSegmentLength($duration)
    {
        $this->setArguments([
            "-hls_time" => $duration,
            "-hls_init_time" => $duration,
            "-force_key_frames" => "expr:gte(t,n_forced*$duration)"
        ]);
        return $this;
    }

    public function setArgument($key, $value = null)
    {
        $this->arguments[$key] = $value;
    }

    public function setArguments($arrArguments)
    {
        $this->arguments = array_merge($this->arguments, $arrArguments);
    }

    # ---------------------------------------------------------------------------------------------
    # Outputs
    # ---------------------------------------------------------------------------------------------

    public function addDefaultResolutions()
    {
        $this->addResolution(new Resolution(196, 144));
        $this->addResolution(new Resolution(320, 240));
        $this->addResolution(new Resolution(640, 360));
        $this->addResolution(new Resolution(854, 480));
        $this->addResolution(new Resolution(1280, 720));
        $this->addResolution(new Resolution(1920, 1080));
        return $this;
    }

    public function addResolution(Resolution $resolution)
    {
        $this->resolutions[$resolution->res] = $resolution;
        return $this;
    }

    public function removeResolution($height)
    {
        if (!array_key_exists($height, $this->resolutions)) return;
        unset($this->resolutions[$height]);
        return $this;
    }

    public function getResolutions()
    {
        return $this->resolutions;
    }

    # ---------------------------------------------------------------------------------------------
    # FFProbe
    # ---------------------------------------------------------------------------------------------
    public function getDuration(){
        $command = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $this->inputFile";
        exec($command, $output, $return_code);
        if ($return_code) {
            array_splice($output, 0, 11);
            throw new \Exception(implode('<br>', $output));
        }
        return floatval($output[0]);
    }

}
