<?php

namespace App;

class HLSParser
{

    private $resolutions = [];

    private $inputFile;
    private $inputFileName;
    private $outputDir;
    private $outputFileName;

    private $ffmpegBinary = "c:/laragon/bin/ffmpeg.exe";

    private $arguments = [
        "-i" => null, // needed input file
        "-s" => null, // needed resolution
        "-profile:v" => "baseline",
        "-level" => "3.0",
        "-start_number" => 0,
        "-hls_time" => 10,
        "-hls_init_time" => 10,
        "-hls_list_size" => 0,
        "-f" => "hls",
        //"-hls_flags" => "split_by_time",
        "-force_key_frames" => "expr:gte(t,n_forced*1)"
    ];

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
        if (is_dir($this->outputDir)) rmdir($this->outputDir);
        if (!is_dir($this->outputDir)) mkdir($this->outputDir);

        foreach ($this->resolutions as $res => $resolution) {
            $this->export($resolution);
        }

        return $this->buildMasterFile();
    }

    /**
     * Generate sliced video + .m3u8 file for given $resolution
     * @param Resolution $resolution
     * @return void
     * @throws \Exception
     */
    private function export(Resolution $resolution)
    {
        $this->setInputFile($this->inputFile);
        $this->setResolution($resolution);
        $cmdString = "$this->ffmpegBinary";
        foreach ($this->arguments as $key => $value) {
            $cmdString .= " $key $value";
        }
        $cmdString .= " $this->outputDir/$this->outputFileName-$resolution->res.m3u8"; // export filename
        $cmdString .= " 1>$this->outputDir/log-$resolution->res.txt 2>&1"; // log SIGERR

        exec($cmdString, $output, $return_code);

        if ($return_code) {
            array_splice($output, 0, 11);
            throw new \Exception(implode('<br>', $output));
        }
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

    private function setInputFile($inputFile)
    {
        $this->arguments["-i"] = $inputFile;
    }

    private function setResolution(Resolution $resolution)
    {
        $this->arguments["-s"] = $resolution->getLabel();
    }

    public function setSegmentLength($duration)
    {
        $this->arguments["-hls_time"] = $duration;
        $this->arguments["-hls_init_time"] = $duration;
        $this->arguments["-force_key_frames"] = "expr:gte(t,n_forced*$duration)";
        return $this;
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

}