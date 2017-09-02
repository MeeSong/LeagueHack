<?php
/**
 * @brief		Editor Extension: Customer Fields and Notes
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Nexus
 * @since		29 Aug 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\nexus\extensions\core\EditorLocations;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor Extension: Customer Fields
 */
class _Customer
{
	/**
	 * Can we use HTML in this editor?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool|null	NULL will cause the default value (based on the member's permissions) to be used, and is recommended in most cases. A boolean value will override that.
	 */
	public function canUseHtml( $member )
	{
		return NULL;
	}
	
	/**
	 * Can we use attachments in this editor?
	 *
	 * @param	\IPS\Member					$member	The member
	 * @param	\IPS\Helpers\Form\Editor	$field	The editor field
	 * @return	bool|null	NULL will cause the default value (based on the member's permissions) to be used, and is recommended in most cases. A boolean value will override that.
	 */
	public function canAttach( $member, $field )
	{
		return NULL;
	}

	/**
	 * Permission check for attachments
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	int|null	$id1	Primary ID
	 * @param	int|null	$id2	Secondary ID
	 * @param	string|null	$id3	Arbitrary data
	 * @param	array		$attachment	The attachment data
	 * @return	bool
	 */
	public function attachmentPermissionCheck( $member, $id1, $id2, $id3, $attachment )
	{
		if ( ( $id3 !== 'note' and $member->member_id == $id1 ) or $member->hasAcpRestriction( 'nexus', 'customers', 'customers_view' ) )
		{
			return TRUE;
		}
		return FALSE;
	}
	
	/**
	 * Attachment lookup
	 *
	 * @param	int|null	$id1	Primary ID
	 * @param	int|null	$id2	Secondary ID
	 * @param	string|null	$id3	Arbitrary data
	 * @return	\IPS\Http\Url|\IPS\Content|\IPS\Node\Model
	 * @throws	\LogicException
	 */
	public function attachmentLookup( $id1, $id2, $id3 )
	{
		if ( \IPS\Dispatcher::i()->controllerLocation === 'admin' )
		{
			return \IPS\nexus\Customer::load( $id1 )->acpUrl();
		}
		else
		{
			return \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=info', 'front', 'clientsinfo', array(), \IPS\Settings::i()->nexus_https );
		}
	}
	
	/**
	 * Rebuild attachment images in non-content item areas
	 *
	 * @param	int|null	$offset	Offset to start from
	 * @param	int|null	$max	Maximum to parse
	 * @return	int			Number completed
	 */
	public function rebuildAttachmentImages( $offset, $max )
	{
		return $this->performRebuild( $offset, $max, array( 'IPS\Text\Parser', 'rebuildAttachmentUrls' ) );
	}

	/**
	 * Rebuild content post-upgrade
	 *
	 * @param	int|null	$offset	Offset to start from
	 * @param	int|null	$max	Maximum to parse
	 * @return	int			Number completed
	 * @note	This method is optional and will only be called if it exists
	 */
	public function rebuildContent( $offset, $max )
	{
		return $this->performRebuild( $offset, $max, array( 'IPS\Text\LegacyParser', 'parseStatic' ) );
	}

	/**
	 * Perform rebuild - abstracted as the call for rebuildContent() and rebuildAttachmentImages() is nearly identical
	 *
	 * @param	int|null	$offset		Offset to start from
	 * @param	int|null	$max		Maximum to parse
	 * @param	callable	$callback	Method to call to rebuild content
	 * @return	int			Number completed
	 */
	protected function performRebuild( $offset, $max, $callback )
	{
		$did	= 0;

		/* Get editor fields */
		$editorFields	= array();

		foreach( \IPS\Db::i()->select( '*', 'nexus_customer_fields', "f_type='Editor'" ) as $field )
		{
			$editorFields[]	= 'field_' . $field['f_id'];
		}

		if( !count( $editorFields ) )
		{
			return $did;
		}

		/* Now update the content */
		foreach( \IPS\Db::i()->select( '*', 'nexus_customers', implode( " IS NOT NULL OR ", $editorFields ) . " IS NOT NULL", 'member_id ASC', array( $offset, $max ) ) as $member )
		{
			$did++;

			/* Update */
			$toUpdate	= array();

			foreach( $editorFields as $fieldId )
			{
				try
				{
					$rebuilt	= call_user_func( $callback, $member[ $fieldId ], \IPS\Member::load( $member['member_id'] ) );
				}
				catch( \InvalidArgumentException $e )
				{
					if( $callback[1] == 'parseStatic' AND $e->getcode() == 103014 )
					{
						$rebuilt	= preg_replace( "#\[/?([^\]]+?)\]#", '', $member[ $fieldId ] );
					}
					else
					{
						throw $e;
					}
				}

				if( $rebuilt )
				{
					$toUpdate[ $fieldId ]	= $rebuilt;
				}
			}

			if( count( $toUpdate ) )
			{
				\IPS\Db::i()->update( 'nexus_customers', $toUpdate, array( 'member_id=?', $member['member_id'] ) );
			}
		}

		return $did;
	}

	/**
	 * Total content count to be used in progress indicator
	 *
	 * @return	int			Total Count
	 */
	public function contentCount()
	{
		/* Get editor fields */
		$editorFields	= array();

		foreach( \IPS\Db::i()->select( '*', 'nexus_customer_fields', "f_type='Editor'" ) as $field )
		{
			$editorFields[]	= 'field_' . $field['f_id'];
		}

		if( !count( $editorFields ) )
		{
			return 0;
		}

		return \IPS\Db::i()->select( 'COUNT(*) as count', 'nexus_customers', implode( " IS NOT NULL OR ", $editorFields ) . " IS NOT NULL" )->first();
	}
}