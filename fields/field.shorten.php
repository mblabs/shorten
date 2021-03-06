<?php


	class fieldShorten extends Field
	{

		protected static $revalidate = '__must-revalidate';

		public function __construct(){
			$this->_name = __('Shorten');
			parent::__construct();
		}


		/**
		 * Test whether this field can be filtered.
		 *
		 * @return boolean
		 *	true if this can be filtered, false otherwise.
		 */
		public function canFilter()
		{
			return true;
		}

		/**
		 * Test whether this field must be unique in a section, that is, only one of
		 * this field's type is allowed per section.
		 *
		 * @return boolean
		 *	true if the content of this field must be unique, false otherwise.
		 */
		public function mustBeUnique()
		{
			return true;
		}

		/**
		 * Display the default settings panel, calls the `buildSummaryBlock`
		 * function after basic field settings are added to the wrapper.
		 *
		 * @see buildSummaryBlock()
		 * @param XMLElement $wrapper
		 *	the input XMLElement to which the display of this will be appended.
		 * @param mixed errors (optional)
		 *	the input error collection. this defaults to null.
		 */
		public function displaySettingsPanel(&$wrapper, $errors=NULL)
		{
			parent::displaySettingsPanel(&$wrapper, $errors=NULL);

			$order = $this->get('sortorder');
			$label = Widget::Label('Url');
			$input = Widget::Input('fields['. $order. '][redirect]', $this->get('redirect'));

			$help = new XMLElement(
				'p',
				__('Absolute or relative. Wrap xpath expressions in curly brackets'). 
				' <code>post/{entry/category/item/@handle}/{entry/@id}</code>.',
				array('class' => 'help')
			);

			$label->appendChild($input);
			$label->appendChild($help);
			$wrapper->appendChild($label);

			$label = Widget::Label();
			$input = Widget::Input("fields[{$order}][hide]", 'yes', 'checkbox');

			if ($this->get('hide') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() .' '. __('Hide this field on publish page'));

			$wrapper->appendChild($label);
			$this->appendShowColumnCheckbox($wrapper);
		}

		/**
		 * Commit the settings of this field from the section editor to
		 * create an instance of this field in a section.
		 *
		 * @return boolean
		 *	true if the commit was successful, false otherwise.
		 */
		public function commit()
		{
			if (!parent::commit() || !($id = $this->get('id')))
				return false;

			$fields = array(
				'field_id' => $id,
				'redirect' => $this->get('redirect'),
				'hide' => $this->get('hide')
			);

			Symphony::Database()->query(
				sprintf('DELETE FROM `tbl_fields_%s` WHERE `field_id` = %s LIMIT 1', $this->handle(), $id)
			);

			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

		/**
		 * Process the raw field data.
		 *
		 * @param mixed $data
		 *	post data from the entry form
		 * @param reference $status
		 *	the status code resultant from processing the data.
		 * @param boolean $simulate (optional)
		 *	true if this will tell the CF's to simulate data creation, false
		 *	otherwise. this defaults to false. this is important if clients
		 *	will be deleting or adding data outside of the main entry object
		 *	commit function.
		 * @param mixed $entry_id (optional)
		 *	the current entry. defaults to null.
		 * @return array[string]mixed
		 *	the processed field data.
		 */
		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=null)
		{
			$status = self::__OK__;

			return array(
				'value' => self::$revalidate
			);
		}

		/**
		 * Display the default data-source filter panel.
		 *
		 * @param XMLElement $wrapper
		 *	the input XMLElement to which the display of this will be appended.
		 * @param mixed $data (optional)
		 *	the input data. this defaults to null.
		 * @param mixed errors (optional)
		 *	the input error collection. this defaults to null.
		 * @param string $fieldNamePrefix
		 *	the prefix to apply to the display of this.
		 * @param string $fieldNameSuffix
		 *	the suffix to apply to the display of this.
		 */
		public function displayDatasourceFilterPanel(XMLElement &$wrapper, $data = null, $errors = null, $fieldnamePrefix = null, $fieldnamePostfix = null)
		{
			parent::displayDatasourceFilterPanel(&$wrapper, $data, $errors, $fieldnamePrefix, $fieldnamePostfix);

			$wrapper->appendChild(
				new XMLElement('p',
					__('append %s to prevent redirect.', array(' <code>no-redirect</code> ')),
					array('class' => 'help')
				));
		}

		/**
		 * Construct the SQL statement fragments to use to retrieve the data of this
		 * field when utilized as a data source.
		 *
		 * @param array $data
		 *	the supplied form data to use to construct the query from??
		 * @param string $joins
		 *	the join sql statement fragment to append the additional join sql to.
		 * @param string $where
		 *	the where condition sql statement fragment to which the additional
		 *	where conditions will be appended.
		 * @param boolean $andOperation (optional)
		 *	true if the values of the input data should be appended as part of
		 *	the where condition. this defaults to false.
		 * @return boolean
		 *	true if the construction of the sql was successful, false otherwise.
		 */
		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false)
		{
			list($shorten, $redirect) = array_map('trim', explode(' ', $data[0]));
			if (!$shorten || $shorten == 'no-redirect') return true;

			$entry_id = self::decode($shorten);

			// if the expression has already been compiled
			$query = 'select value from tbl_entries_data_%s where entry_id = %s';

			$data  = Symphony::Database()->fetchVar(
				'value', 0, sprintf($query, $this->get('id'), $entry_id)
			);

			$redirect = ($redirect == 'no-redirect') ? false : true;
			if ($data && $data !== self::$revalidate && $redirect)
				self::redirect($data);

			$where .= ' AND e.id = '. $entry_id;

			$this->shorten  = $shorten;
			$this->redirect = $redirect;
			return true;
		}

		/**
		 * Append the formatted xml output of this field as utilized as a data source.
		 *
		 * @param XMLElement $wrapper
		 *	the xml element to append the xml representation of this to.
		 * @param array $data
		 *	the current set of values for this field. the values are structured as
		 *	for displayPublishPanel.
		 * @param boolean $encode (optional)
		 *	flag as to whether this should be html encoded prior to output. this
		 *	defaults to false.
		 * @param string $mode
		 *	 A field can provide ways to output this field's data. For instance a mode
		 *  could be 'items' or 'full' and then the function would display the data
		 *  in a different way depending on what was selected in the datasource
		 *  included elements.
		 * @param number $entry_id (optional)
		 *	the identifier of this field entry instance. defaults to null.
		 */
		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null)
		{
			$data = $data['value'];

			if ($this->shorten)
			{
				if ($data == self::$revalidate)
					$data = $this->compile($entry_id);

				if ($this->redirect && $data)
					self::redirect($data);
			}

			$shorten = self::encode($entry_id);

			$wrapper->appendChild(new XMLElement(
				$this->get('element_name'),
				$data == self::$revalidate ? '' : $data,
				array('handle' => $shorten)
			));
		}

		/**
		 * Format this field value for display in the publish index tables.
		 *
		 * @param array $data
		 * an associative array of data for this string. At minimum this requires a
		 * key of 'value'.
		 * @param XMLElement $link (optional)
		 * an xml link structure to append the content of this to provided it is not
		 * null. it defaults to null.
		 * @return string
		 * the formatted string summary of the values of this field instance.
		 */
		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null)
		{
			$data = $data['value'];
			if ($data == self::$revalidate)
				$data = $this->compile($entry_id);

			if ($link)
			{
				$link->setValue($data);
				return $link->generate();
			}

			return $data;
		}

		/**
		 * Display the publish panel for this field. The display panel is the
		 * interface to create the data in instances of this field once added
		 * to a section.
		 *
		 * @param XMLElement $wrapper
		 *	the xml element to append the html defined user interface to this
		 *	field.
		 * @param array $data (optional)
		 *	any existing data that has been supplied for this field instance.
		 *	this is encoded as an array of columns, each column maps to an
		 *	array of row indexes to the contents of that column. this defaults
		 *	to null.
		 * @param mixed $flagWithError (optional)
		 *	flag with error defaults to null.
		 * @param string $fieldnamePrefix (optional)
		 *	the string to be prepended to the display of the name of this field.
		 *	this defaults to null.
		 * @param string $fieldnameSuffix (optional)
		 *	the string to be appended to the display of the name of this field.
		 *	this defaults to null.
		 * @param number $entry_id (optional)
		 *	the entry id of this field. this defaults to null.
		 */
		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null)
		{
			if ($this->get('hide') == 'yes') return;
			if (!$entry_id || !$data) return;

			if ($data['value'] == self::$revalidate)
				$data['value'] = $this->compile($entry_id);

			$label = Widget::Label($this->get('label'));
			$span  = new XMLElement('span', null, array('class' => 'frame'));
			$short = new XMLElement('div',
				__('This entry has been shortened to').
					' <strong>'. self::encode($entry_id). '</strong>'
				);

			$span->appendChild($short);
			$label->appendChild($span);
			$wrapper->appendChild($label);

			$url  = self::normalizeUrl($data['value']);
			$link = Widget::Anchor($url, $url);
			$span->appendChild($link);
		}


		/**
		 * The default method for constructing the example form markup containing this
		 * field when utilized as part of an event. This displays in the event documentation
		 * and serves as a basic guide for how markup should be constructed on the
		 * Frontend to save this field
		 *
		 * @return XMLElement
		 *	a label widget containing the formatted field element name of this.
		 */
		public function getExampleFormMarkup()
		{
			return null;
		}

		/**
		 * The default field table construction method. This constructs the bare
		 * minimum set of columns for a valid field table. Subclasses are expected
		 * to overload this method to create a table structure that contains
		 * additional columns to store the specific data created by the field.
		 */
		public function createTable()
		{
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `value` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `value` (`value`)
				) ENGINE=MyISAM;"
			);
		}
		

		/*
		 * -----------------------------------------------------------------
		 * Stolen from: http://snipplr.com/view/22246/base62-encode--decode/
		 *
		 */
		public static function encode($val, $base=62, $chars='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
		{
			// can't handle numbers larger than 2^31-1 = 2147483647
			$str = '';

			do {
				$i = $val % $base;
				$str = $chars[$i] . $str;
				$val = ($val - $i) / $base;
			} while($val > 0);

			return $str;
		}

		public static function decode($str, $base=62, $chars='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
		{
			$len = strlen($str);
			$val = 0;
			$arr = array_flip(str_split($chars));

			for($i = 0; $i < $len; ++$i)
				$val += $arr[$str[$i]] * pow($base, $len-$i-1);

			return $val;
		}

		/*
		 * -----------------------------------------------------------------
		 */


		public function compile($entry_id)
		{
			require_once EXTENSIONS. '/shorten/lib/data.shorten.php';

			$section_id = $this->get('parent_section');
			$ds = new datasource_Shorten();

			$fields = Symphony::Database()->fetch(
				sprintf(
					"SELECT element_name FROM `tbl_fields` WHERE `parent_section` = %d",
					$section_id
				)
			);

			foreach($fields as $field)
				$ds->dsParamINCLUDEDELEMENTS[] = $field['element_name'];

			$ds->dsParamLIMIT = 1;
			$ds->dsParamSTARTPAGE = '1';
			$ds->dsParamROOTELEMENT = 'aaa';
			$ds->dsParamSORT = 'system:id';
			$ds->dsParamASSOCIATEDENTRYCOUNTS = 'no';

			$ds->dsParamFILTERS = array(
				'id' => $entry_id
			);
			$ds->setSource($section_id);

			$params = array();
			$xml = $ds->grab($params)->generate();
			$doc = new DomDocument;
			$doc->preserveWhiteSpace = false;

			$doc->loadXML($xml);

			$xpath = new DOMXPath($doc);
			$full  = $this->get('redirect');
			preg_match_all('/\{(.*?)\}/', $full, $matches);

			$search  = $matches[0];
			$replace = $matches[1];
			foreach ($search as $i => $str)
			{
				$query  = trim($replace[$i], '/');
				$result = $xpath->query($query);

				$new = array();
				foreach ($result as $r) $new[] = $r->nodeValue;

				$full = str_replace($str, join('', $new), $full);
			}

			$this->update($entry_id, $full);
			return $full;
		}

		public function update($entry_id, $value = null)
		{
			if (!$value) $value = self::$revalidate;
			$this->entryDataCleanup($entry_id);

			$table = 'tbl_entries_data_'. $this->get('id');
			$data  = array(
				'value' => $value,
				'entry_id' => $entry_id
			);

			Symphony::Database()->insert($data, $table);
		}

		public static function redirect($url)
		{
			$url = self::normalizeUrl($url);

			header ('Location: '. $url, $replace = true, 301);
			die();
		}

		public static function normalizeUrl($url)
		{
			$parse = parse_url($url);
			if (!$parse['host'])
				$url = URL. '/'. ltrim($url, '/');

			return $url;
		}
	}
