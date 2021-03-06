<?php

	require_once TOOLKIT. '/class.datasource.php';

	class datasource_Shorten extends SectionDatasource
	{
		protected $source;

		public function setSource($source)
		{
			$this->source = $source;
		}

		public function getSource()
		{
			return $this->source;
		}

		public function grab(array &$param_pool=NULL){
			$result = new XMLElement($this->dsParamROOTELEMENT);
			$this->_param_output_only = false;

			try{
				$result = $this->execute($param_pool);
			}
			catch(FrontendPageNotFoundException $e){
				// Work around. This ensures the 404 page is displayed and
				// is not picked up by the default catch() statement below
				FrontendPageNotFoundExceptionHandler::render($e);
			}
			catch(Exception $e){
				$result->appendChild(new XMLElement('error', $e->getMessage()));
				return $result;
			}


			if ($this->_force_empty_result) $result = $this->emptyXMLSet();

			return $result;
		}
	}
