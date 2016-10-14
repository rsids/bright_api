<?php 
namespace fur\bright\exceptions;

class UploadException extends GenericException  {
	const UPLOAD_FAILED = 1;
	const INVALID_EXTENSION = 2;
	const FILE_TOO_BIG = 3;
	const INVALID_NAME = 4;
	const INVALID_PATH = 5;
	const NOT_AN_IMAGE = 6;
	
	public static function getErrorMessage($exception) {
		switch($exception) {
			case UploadException::UPLOAD_FAILED:
				return "The file failed to upload";
				
			case UploadException::INVALID_EXTENSION:
				return "The uploaded file has an extension which is not allowed";
				
			case UploadException::NOT_AN_IMAGE:
				return "The uploaded file is not an image";
				
			case UploadException::FILE_TOO_BIG:
				return "The uploaded file is too big";
				
			case UploadException::INVALID_NAME:
				return "The specified name is invalid";
				
			case UploadException::INVALID_PATH:
				return "The specified path is invalid";
				
		}
	}
}