<?php
/**
*	Class Posts
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0
*
**/

namespace Jeff\Api\Models;

Class Posts extends Model
{

	// the Model Configuration
	public $modelName = 'post';
	public $modelNamePlural = 'posts';

	protected $searchSendCols = Array('id', 'date', 'amountTotal', 'note', 'recipient');	// what data (=db-fields) to send when querying a search 
	protected $dbTable = "posts";

	public $dbDefinition = array( 	
			array('id', 'int', '11', false, null, 'AUTO_INCREMENT'),
			array('date', 'date', null, true),
			//array('amountTotal', 'decimal', '10,2', true, '0', null),
			
			array('title', 'varchar', '50', false),
			array('body', 'varchar', '250', false, false),
			// array('currencyRate', 'decimal', '10,5', true, '1', null),
			// array('amountTotalC', 'decimal', '10,2', true, false, null),
			// array('nettoC', 'decimal', '10,2', true, '0', null),
			// array('ustC', 'decimal', '10,2', false, '0', null),
			// array('taxAirlineTicketC', 'decimal', '10,2', true, '0', null),
			// array('taxKurtaxeC', 'decimal', '10,2', true, '0', null),
			// array('taxOtherC', 'decimal', '10,2', true, '0', null),


			// array('recipient', 'varchar', '100', false, false, null),
			// array('uid', 'varchar', '50', false, false, null),
			// array('note', 'varchar', '255', false, false, null),
			
			// array('category', 'int', '11', true, false, null),
			// array('taxAccount', 'int', '11', true, null, null),
			// array('inTax', 'tinyint', '1', false, false, null),

			// array('invoiceNumber', 'varchar', '50', true, false, null),
			// array('invoiceDate', 'date', null, true, null, null),
			// array('invoiceIBAN', 'varchar', '30', false, false, null),
			// array('invoiceBIC', 'varchar', '30', false, false, null),
			array('modBy', 'int', '11', true),
		);
	public $dbPrimaryKey = 'id';
	public $dbKeys = array(
		array('date', array('date')),
		array('text', array('title', 'body')),


		);
}

?>