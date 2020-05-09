<?php
/**
 * Created by PhpStorm.
 * User: bruce
 * Date: 2019-07-24
 * Time: 16:18
 */

namespace uploader;

use Ks3Client;

class UploadKs3 extends Upload{
    public $accessKey;
    public $secretKey;
    public $bucket;
    //即domain，域名
    public $endpoint;
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
	    
        $this->accessKey = $ServerConfig['accessKey'];
        $this->secretKey = $ServerConfig['accessSecret'];
        $this->bucket = $ServerConfig['bucket'];
        $this->endpoint = $ServerConfig['endpoint'];
	    $this->domain = $ServerConfig['domain'] ?? '';
	    //默认域名：https://ks3-cn-guangzhou.ksyun.com（与不同区域有关）
	    $defaultDomain = 'https://' . $this->endpoint;
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
	 * Upload files to KingSoft KS3(KingSoft Standard Storage Service)
	 * @param $key
	 * @param $uploadFilePath
	 *
	 * @return array
	 */
	public function upload($key, $uploadFilePath){
	    try {
	    	if($this->directory){
			    $key = $this->directory . '/' . $key;
		    }
	    	
		    $client = new Ks3Client($this->accessKey, $this->secretKey, $this->endpoint);
	    	$fp = fopen($uploadFilePath, 'rb');
		    $res = $client->putObjectByFile([
		    	'Bucket' => $this->bucket,
		    	'Key' => $key,
			    "ACL"=>"public-read",//可以设置访问权限,合法值,private、public-read
		    	'Content' => [
		    		'content' => $fp,
				    'seek_position' => 0,
			    ],
		    ]);
		    is_resource($fp) && fclose($fp);
		    
		    if(!isset($res['ETag'])){
			    throw new Exception(var_export($res, true));
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