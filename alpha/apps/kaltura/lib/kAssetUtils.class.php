<?php
class kAssetUtils
{
	public static function createAssets ( $list , $list_name )
	{
		foreach ( $list as $entry )
		{
			$is_ready = $entry->getStatus() == entryStatus::READY;
			$data = $entry->getDataPath();

			// this should not happen !
			$duration =  $entry->getLengthInMsecs();
			if ( $duration == NULL || $duration <= 0 )
			{
				$duration = 10.0;
			}
			else
			{
				$duration = $duration/1000;
			}

			$source_link = '';
			$credit = $entry->getCredit();
			if ($credit == null)
			{
				$credit = '';
			}
			else
			{
				$source_link = $entry->getSourceLink();
				if ($source_link == null)
					$source_link = '';
			}
			echo "\t" .  baseObjectUtils::objToXml ( $entry , array ( 'id' , 'name' , 'media_type' , 'kshow_id' ) ,
			'asset' , true ,
			array ( 'url' => $data , 'ready' => $is_ready , 'thumbnail_path' => $entry->getThumbnailPath() ,
			'credit' => $credit, 'source_link' => $source_link,
			'duration' => $duration  , 'list_type'=>$list_name , 'contributor_screen_name' => $entry->getKuser()->getScreenName() ));
		}
	}

	/**
	 * @param asset $asset
	 * @params entry $entry
	 * @return string
	 */
	public static function getFileName(entry $entry, flavorAsset $flavorAsset = null)
	{
		$fileExt = "";
		$fileBaseName = $entry->getName();
		if ($flavorAsset)
		{
			$flavorParams = $flavorAsset->getFlavorParams();
			if ($flavorParams)
				$fileBaseName = ($fileBaseName . " (" . $flavorParams->getName() . ")");

			$fileExt = $flavorAsset->getFileExt();
		}
		else
		{
			$syncKey = $entry->getSyncKey(entry::FILE_SYNC_ENTRY_SUB_TYPE_DATA);
			list($fileSync, $local) = kFileSyncUtils::getReadyFileSyncForKey($syncKey, true, false);
			if ($fileSync)
				$fileExt = $fileSync->getFileExt();
		}
	
		return array($fileBaseName, $fileExt);
	}

	public static function getAssetUrl(asset $asset, $servePlayManifest = false , $playManifestClientTag = null , $storageId = null, $urlParameters = '')
	{
		$partner = PartnerPeer::retrieveByPK($asset->getPartnerId());
		if(!$partner)
			return null;
	
		$syncKey = $asset->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
		$externalStorageUrl = self::getExternalStorageUrl($partner, $asset, $syncKey, $servePlayManifest, $playManifestClientTag, $storageId);
		if($externalStorageUrl)
			return $externalStorageUrl;
			
		if($partner->getStorageServePriority() == StorageProfile::STORAGE_SERVE_PRIORITY_EXTERNAL_ONLY)
			return null;

		if($asset instanceof flavorAsset && $servePlayManifest)
		{
			$url =  requestUtils::getApiCdnHost() . $asset->getPlayManifestUrl($playManifestClientTag , $storageId);
		}
		else
		{
			$urlManager = DeliveryProfilePeer::getDeliveryProfile($asset->getEntryId());
			if($asset instanceof flavorAsset)
				$urlManager->initDeliveryDynamicAttributes(null, $asset);
			$profileAttributes = $urlManager->getDynamicAttributes();
			$profileAttributes->setUrlParams($urlParameters);

			$url = $urlManager->getFullAssetUrl($asset);

			$url = preg_replace('/^https?:\/\//', '', $url);

			$protocol = infraRequestUtils::getProtocol();
			$deliveryProfileProtocols = $urlManager->getMediaProtocols();
			if (!is_null($deliveryProfileProtocols) && !in_array($protocol, explode(',',$deliveryProfileProtocols)))
				$protocol = infraRequestUtils::PROTOCOL_HTTP;
			$url = $protocol . "://" . $url;
		}
		
		return $url;
	}

	private static function getExternalStorageUrl(Partner $partner, asset $asset, FileSyncKey $key, $servePlayManifest = false , $playManifestClientTag = null , $storageId = null)
	{
		if(!$partner->getStorageServePriority() || $partner->getStorageServePriority() == StorageProfile::STORAGE_SERVE_PRIORITY_KALTURA_ONLY)
			return null;
			
		if(is_null($storageId) && $partner->getStorageServePriority() == StorageProfile::STORAGE_SERVE_PRIORITY_KALTURA_FIRST)
			if(kFileSyncUtils::getReadyInternalFileSyncForKey($key)) // check if having file sync on kaltura dcs
				return null;
				
		$fileSync = kFileSyncUtils::getReadyExternalFileSyncForKey($key, $storageId);
		if(!$fileSync)
			return null;
			
		$storage = StorageProfilePeer::retrieveByPK($fileSync->getDc());
		if(!$storage)
			return null;
			
		if($servePlayManifest)
		{
			$deliveryProfile = DeliveryProfilePeer::getRemoteDeliveryByStorageId($storageId, $asset->getEntryId(), PlaybackProtocol::HTTP, "https");
			
			if (is_null($deliveryProfile))
				$url = infraRequestUtils::PROTOCOL_HTTP . "://" . kConf::get("cdn_api_host");
			else
				$url = requestUtils::getApiCdnHost();

			$url .= $asset->getPlayManifestUrl($playManifestClientTag ,$storageId); 
		}
		else
		{
			$urlManager = DeliveryProfilePeer::getRemoteDeliveryByStorageId($fileSync->getDc(), $asset->getEntryId());
			if($urlManager) {
				$dynamicAttrs = new DeliveryProfileDynamicAttributes();
				$dynamicAttrs->setFileExtension($asset->getFileExt());
				$urlManager->setDynamicAttributes($dynamicAttrs);
				
				$url = rtrim($urlManager->getUrl(),'/') . '/' . ltrim($urlManager->getFileSyncUrl($fileSync),'/');
			} else {
				KalturaLog::debug("Couldn't determine delivery profile for storage id");
				$url = null;
			}
		}
		
		return $url;
	}
}
