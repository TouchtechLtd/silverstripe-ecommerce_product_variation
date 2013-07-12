<?php


class CreateEcommerceVariations extends Controller {

	private static $allowed_actions = array(
		"jsonforform" => "ADMIN",
		"createvariations",
		"select",
		"rename",
		"add",
		"remove",
		"move",
		'cansavevariation'
	);

	protected $_productID = 0;
	protected $_product = null;
	protected $_typeorvalue = "type"; // or value!
	protected $_classname = "type"; // or value!
	protected $_namefield = "Name";
	protected $_labelfield = "Label"; // only for ProductAttributeType
	protected $_id = 0;
	protected $_value = "";
	protected $_position = -1; //use -1 to distinguish it from 0 (first in sorting order)
	protected $_message = ""; //use -1 to distinguish it from 0 (first in sorting order)
	protected $_messageclass = "good"; //use -1 to distinguish it from 0 (first in sorting order)
	protected $_selectedtypeid = array(); //use -1 to distinguish it from 0 (first in sorting order)
	protected $_selectedvalueid = array(); //use -1 to distinguish it from 0 (first in sorting order)
	protected $output = "";

	private static $session_name_for_selected_values = "SelectecedValues";

	function init() {
		parent::init();
		$shopAdminCode = EcommerceConfig::get("EcommerceRole", "admin_permission_code");
		if(!Permission::check("CMS_ACCESS_CMSMain") && !Permission::check($shopAdminCode)) {
			Security::permissionFailure($this, _t('Security.PERMFAILURE',' This page is secured and you need CMS rights to access it. Enter your credentials below and we will send you right along.'));
		}
		if(isset($_GET["typeorvalue"])) { $this->_typeorvalue = $_GET["typeorvalue"];}
		if(isset($_GET["id"])) { $this->_id = intval($_GET["id"]);}
		if(isset($_GET["value"])) { $this->_value = urldecode($_GET["value"]);}
		if(isset($_GET["position"])) { $this->_position = intval($_GET["_position"]);}
		if($this->_typeorvalue == "type") {
			$this->_classname = 'ProductAttributeType';
			$this->_namefield = 'Name';
		}
		else {
			$this->_classname = 'ProductAttributeValue';
			$this->_namefield = 'Value';
		}
		$this->_productID = $this->request->param("ProductID");
		$this->_product = Product::get()->byID($this->_productID);
		if(!$this->_product) {
			user_error("could not find product for ID: ".$this->_productID, E_USER_WARNING);
		}
		$this->_selectedtypeid = $this->_product->getArrayOfLinkedProductAttributeTypeIDs();
		$this->_selectedvalueid = $this->_product->getArrayOfLinkedProductAttributeValueIDs();
	}

	public function index() {
		return 10;
	}

	public function Output() {
		return $this->output;
	}

	function createvariations() {
		$missingTypesID = array(-1 => -1);
		foreach($this->_selectedtypeid as $typeID) {
			if(! isset($_GET[$typeID])) {
				$type = ProductAttributeType::get()->byID($typeID);
				if($type) {
					$missingTypes[] = $type->Name;
				}
				$missingTypesID[] = $typeID;
			}
		}
		/*
		if(isset($missingTypes)) {
			$this->_message = 'No variations has been created because you\'ve not selected values for the type' . (count($missingTypes) > 1 ? 's ' : ' ') . implode(', ', $missingTypes) . '.';
			$this->_messageclass = 'bad';
			return $this->jsonforform();
		}
		*/
		$missingTypes = array();
		$types = ProductAttributeType::get()->exclude(array("ID" => implode(",", $missingTypesID)));
		if($types->count()) {
			$values = array();
			foreach($types as $type) {
				if(isset($_GET[$type->ID])) {
					$values[$type->ID] = explode(',', $_GET[$type->ID]);
				}
			}
			$cpt = 0;
			if(count($values) > 0) {
				$cpt = $this->_product->generateVariationsFromAttributeValues($values);
				$this->_selectedtypeid = $this->_product->getArrayOfLinkedProductAttributeTypeIDs();
				$this->_selectedvalueid = $this->_product->getArrayOfLinkedProductAttributeValueIDs();
			}
			if($cpt > 0) {
				$this->_message = ($cpt == 1 ? '1 new variation has' : "$cpt new variations have") . ' been created successfully';
			}
			else {
				$this->_message = 'No new variations created';
			}
		}
		else {
			$this->_message = 'No attribute types';
		}
		$this->output = $this->jsonforform();
		return $this->output;
	}

	function jsonforform() {	
		if(! $this->_message) {
			$this->_message = _t("CreateEcommerceVariations.STARTEDITING", "Start editing the list below to create variations.");
		}
		$result['Message'] = $this->_message;
		$result['MessageClass'] = $this->_messageclass;
		$types = ProductAttributeType::get();
		if($types->count()) {
			foreach($types as $type) {
				$resultType = array(
					'ID' => $type->ID,
					'Name' => $type->Name,
					'Checked' => isset($this->_selectedtypeid[$type->ID]),
					'Disabled' => ! $this->_product->canRemoveAttributeType($type),
					'CanDelete' => $type->canDelete()
				);
				$values = $type->Values();
				if($values) {
					foreach($values as $value) {
						$resultType['Values'][] = array(
							'ID' => $value->ID,
							'Name' => $value->Value,
							'Checked' => isset($this->_selectedvalueid[$value->ID]),
							'CanDelete' => $value->canDelete()
						);
					}
				}
				$result['Types'][] = $resultType;
			}
		}
		$this->output =  Convert::array2json($result);
		return $this->output;
	}

	function select() {
		// is it type of Value?
		// if type is value -> create / delete Product Variation (if allowed)
		// elseif type is type - > add / remove selection...
		$this->_product->addAttributeType($obj);
		$this->_product->removeAttributeType($obj);
		die("not completed yet");
		$this->output =  $this->jsonforform();
		return $this->output;
	}

	function rename() {
		//is it Type or Value?
		$className = $this->_classname;
		$obj = $className::get()->byID($this->_id);
		if($obj) {
			$name = $obj->{$this->_namefield};
			if($obj instanceOf ProductAttributeType) {
				$obj->{$this->_labelfield} = $this->_value;
				$name .= " (".$obj->{$this->_labelfield}.")";
			}
			$obj->{$this->_namefield} = $this->_value;
			$obj->write();
			$this->_message = _t("CreateEcommerceVariations.HASBEENRENAMED","$name has been renamed to ".$this->_value,".");
		}
		else {
			$this->_message = _t("CreateEcommerceVariations.CANNOTBEFOUND","Entry can not be found.");
			$this->_messageclass = "bad";
		}
		$this->output =  $this->jsonforform();
		return $this->output;
	}

	function add() {
		//is it Type or Value?
		$obj = new $this->_classname();
		$obj->{$this->_namefield} = $this->_value;
		if($this->_id) {
			$obj->TypeID = $this->_id;
			$obj->write();
		}
		else {
			$obj->write();
			/*if($obj instanceOf ProductAttributeType) {
				$this->_product->addAttributeType($obj);
			}
			else {
				user_error($obj->Title ." should be an instance of ProductAttributeType", E_USER_WARNING);
			}*/
		}
		$this->_message = $this->_value.' '._t("CreateEcommerceVariations.HASBEENADDED",'has been added.');
		$this->output =  $this->jsonforform();
		return $this->output;
	}

	function remove() {
		//is it Type or Value?
		$className = $this->_classname;
		$obj = $className::get()->byID($this->_id);
		if($obj) {
			$name = $obj->{$this->_namefield};
			if($obj->canDelete()) {
				if($this->_typeorvalue == "type") {
					$this->_product->removeAttributeType($obj);
				}
				$obj->delete();
				$obj->destroy();
				$this->_selectedtypeid = $this->_product->getArrayOfLinkedProductAttributeTypeIDs();
				$this->_selectedvalueid = $this->_product->getArrayOfLinkedProductAttributeValueIDs();
				$this->_message = _t("CreateEcommerceVariations.HASBEENDELETED","$name has been deleted.");
			}
			else {
				$this->_message = _t("CreateEcommerceVariations.CANNOTBEDELETED","$name can not be deleted (it is probably used in a sale).");
				$this->_messageclass = "bad";
			}
		}
		else {
			$this->_message = _t("CreateEcommerceVariations.CANNOTBEFOUND","Entry can not be found.");
			$this->_messageclass = "bad";
		}
		$this->output =  $this->jsonforform();
		return $this->output;
	}

	function move() {
		//is it Type or Value?
		//move Item
		die("not completed yet");
		$this->output =  "ok";
		return $this->output;
	}

	function cansavevariation() {
		$variation = null;
		if(isset($_GET['variation'])) {
			$obj = ProductVariation::get()->byID(intval($_GET['variation']));
		}
		foreach($this->_selectedtypeid as $typeID) {
			if(isset($_GET[$typeID])) {
				$value = $_GET[$typeID];
				if(! $variation && ! $value) { 
					$this->output =  false;
					return $this->output;
				}
				if($value) {
					$values[$typeID] = $value;
				}
			}
			else {
				$this->output =  false;
				return $this->output;
			}
		}
		$variations = $this->_product->getComponents('Variations', $variation ? "\"ProductVariation\".\"ID\" != '$variation->ID'" : '');
		foreach($variations as $otherVariation) {
			$otherValues = DB::query("
				SELECT \"TypeID\", \"ProductAttributeValueID\"
				FROM \"ProductVariation_AttributeValues\"
					INNER JOIN \"ProductAttributeValue\" ON \"ProductAttributeValue\".\"ID\" = \"ProductAttributeValueID\"
				WHERE \"ProductVariationID\" = '$otherVariation->ID' ORDER BY \"TypeID\""
			)->map()->toArray();
			if($otherValues == $values) {
				$this->output =  false;
				return $this->output;
			}
		}
		$this->output =  true;
		return $this->output;

	}


}


class CreateEcommerceVariations_Field extends LiteralField {

	function __construct($name, $additionalContent = '', $productID) {
		Requirements::themedCSS("CreateEcommerceVariationsField", "ecommerce_product_variation");
		$additionalContent .= $this->renderWith("CreateEcommerceVariations_Field");
		parent::__construct($name, $additionalContent);
	}

	function ProductVariationGetPluralName() {
		return Convert::raw2att(ProductVariation::get_plural_name());
	}

	function ProductAttributeTypeGetPluralName() {
		return Convert::raw2att(ProductAttributeType::get_plural_name());
	}
	function ProductAttributeValueGetPluralName() {
		return Convert::raw2att(ProductAttributeValue::get_plural_name());
	}

	function CheckboxField($name, $title) {
		return new CheckboxField($name, $title);
	}
	function TextField($name, $title) {
		return new TextField($name, $title);
	}

	function AttributeSorterLink() {
		$singleton = singleton("ProductAttributeType");
		if(class_exists("DataObjectSorterController") && $singleton->hasExtension("DataObjectSorterDOD")) {
			return DataObjectSorterController::popup_link($className = "ProductAttributeType", $filterField = "", $filterValue = "", $linkText = "Sort Types");
		}
	}
	function ValueSorterLink() {
		$singleton = singleton("ProductAttributeValue");
		if(class_exists("DataObjectSorterController") && $singleton->hasExtension("DataObjectSorterDOD")) {
			return DataObjectSorterController::popup_link($className = "ProductAttributeValue", $filterField = "TypeChangeToId", $filterValue = "ID", $linkText = "Sort Values");
		}
	}
}
