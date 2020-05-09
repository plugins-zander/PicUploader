<?php
/**
 * Created by PhpStorm.
 * User: bruce
 * Date: 2018-09-06
 * Time: 15:00
 */

namespace uploader;

use Exception;
use Aws\S3\S3Client;

class UploadJd extends Upload{

    public $accessKey;
    public $secretKey;
    public $endpoint;
    public $bucket;
    public $region;
	public $domain;
	public $directory;
	//上传目标服务器名称
	public $uploadServer;
	
    //config from config.php, using static because the parent class needs to use it.
    public static $config;
    //arguments from php client, the image absolute path
    public $argv;

    /**
     * Upload constructor.
     *
     * @param $params
     */
    public function __construct($params)
    {
	    $ServerConfig = $params['config']['storageTypes'][$params['uploadServer']];
	    
        $this->accessKey = $ServerConfig['AccessKeyId'];
        $this->secretKey = $ServerConfig['AccessKeySecret'];
        $this->endpoint = $ServerConfig['endpoint'];
        $this->bucket = $ServerConfig['bucket'];
        $this->region = $ServerConfig['region'];
	    $this->domain = $ServerConfig['domain'] ?? '';
	    //https://markdown.s3.cn-south-1.jcloudcs.com/2018/11/28/bc4443f413b4eb32b3964d9c8e1fe755.jpeg
	    $defaultDomain = 'https://' . $this->bucket . '.s3.' . $this->region . '.jcloudcs.com';
	    !$this->domain && $this->domain = $defaultDomain;
	    
	    if(!isset($ServerConfig['directory']) || ($ServerConfig['directory']=='' && $ServerConfig['directory']!==false)){
		    //如果没有设置，使用默认的按年/月/日方式使用目录
		    $this->directory = date('Y/m/d');
	    }else{
		    //设置了，则按设置的目录走
		    $this->directory = trim($ServerConfig['directory'], '/');
	    }
	    $this->uploadServer = ucfirst($params['uploadServer']);

        $this->argv = $params['argv'];
        static::$config = $params['config'];
    }
	
	/**
	 * Upload files to JDcloud OSS(Object Storage Service)
	 * @param $key
	 * @param $uploadFilePath
	 *
	 * @return array
	 */
	public function upload($key, $uploadFilePath){
	    try {
		    $s3Client = new S3Client([
			    'version' => 'latest',
			    'region' => $this->region,
			    'endpoint' => $this->endpoint,
			    'credentials' => [
				    'key' => $this->accessKey,
				    'secret' => $this->secretKey,
			    ],
		    ]);
		    
		    if($this->directory){
			    $key = $this->directory . '/' . $key;
		    }
			
		    $fp = fopen($uploadFilePath, 'rb');
		    $retObj = $s3Client->upload($this->bucket, $key, $fp, 'public');
		    is_resource($fp) && fclose($fp);
		    
		    if(!is_object($retObj)){
			    throw new Exception(var_export($retObj, true));
		    }
		
		    $data = [
			    'code' => 0,
			    'msg' => 'success',
			    'key' => $key,
			    'domain' => $this->domain,
		    ];
	    } catch (Exception $e) {
		    //上传出错，记录错误日志(为了保证统一处理那里不出错，虽然报错，但这里还是返回对应格式)
		    $data = [
			    'code' => -1,
			    'msg' => $e->getMessage(),
		    ];
		    $this->writeLog(date('Y-m-d H:i:s').'(' . $this->uploadServer . ') => '.$e->getMessage() . "\n\n", 'error_log');
	    }
        return $data;
    }
}