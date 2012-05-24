<?php
class OptionButtons
// create HTML selection list from MODx template variable definition
// buildSet will return an option set or listbox with setName as Name
// function "selected" returns array of selected option labels & values
// with pubKit E1.0.0 October 2011

{
	public $options;
	public $setName;
	public $definition;
	public $defaultValue;

function __construct($tv, $setName) {
	global $modx;
	$this->definition = $modx->getTemplateVar($tv,'type,elements,default_text');
    $this->type = $this->definition['type'];
	$this->setName = $setName;
	$this->options = array();
	$unpack = explode("||",$this->definition['elements']);
	for ($j=0;$j<count($unpack);$j++) {
		$elements = explode("==",$unpack[$j]);
		$this->options[$elements[0]]= (isset($elements[1])) ? $elements[1] : $elements[0];
		}
	$this->defaultValue = $this->definition['default_text'];
}

function buildSet($selected=array(), $className="", $labelPos='') {
	$output = "\n";
	if (!is_array($selected)) {
		$selected = array($selected);
	}

	if ($this->type == 'option') {
        $selected = $selected[0];

		foreach ($this->options as $optName =>$option) {
			if (isset($optName)) {

				if ($labelPos == 'R'  OR empty($labelPos)) {
					$labelOpen = '<label>';
					$labelClose = $optName . '</label>';
				}

				elseif ($labelPos == 'L') {
					$labelOpen = '<label>' . $optName;
					$labelClose = '</label>';
				}
			}

		$output .= (empty($className)) ? "" : '<span class="' . $className . '"> ';
		$output .= $labelOpen . '<input ';
		$output .= 'type="radio" name="' . $this->setName . '" ';
           $output .= 'value="'.$option.'"';
   		if (!empty($selected) && $selected == $option) {
			$output .= ' checked="checked"';
		}
        $output .= ' />' . $labelClose . "\n";
		$output .= (empty($className)) ? "" : "</span>\n";
		}
	}

	elseif ($this->type == 'checkbox') {
        foreach ($this->options as $optName =>$option) {
			if (isset($optName)) {

				if ($labelPos == 'R'  OR empty($labelPos)) {
					$labelOpen = '<label>';
					$labelClose = $optName . '</label>';
				}

				elseif ($labelPos == 'L') {
					$labelOpen = '<label>' . $optName;
					$labelClose = '</label>';
				}
			}

			$output .= (empty($className)) ? "" : '<span class="' . $className.'"> ';
			$output .= $labelOpen . '<input ';
			$output .= 'type="checkbox" name="' . $this->setName . '[]" ';
            $output .= 'value="' . $option.'"';
	   		if (!empty($selected) && in_array($option,$selected)) {
				$output .= ' checked="checked"';
			}
			$output .= ' />' . $labelClose . "\n";
			$output .= (empty($className)) ? "" : "</span>\n";
		}
	}

	elseif ($this->type == 'listbox') {
        $selected = $selected[0];
		if (empty($selected)) {$selected = $this->defaultValue;}
		$class = (empty($className)) ? "" : ' class="' . $className . '"';
		$output .= '<select id="' . $this->setName . '"';
		$output .= ' name="' . $this->setName . '"' . $class . ">\n";
		foreach ($this->options as $optName =>$option) {
			$output .= '<option value="' . $option . '"';
			if (!empty($selected) && $selected == $option) {
				$output .= ' selected="selected"';
			}
			$output .= '>' . $optName . "</option>\n";
		}
		$output .= "</select>\n\n";

	}

	elseif ($this->type == 'listbox-multiple') {
		$class = (empty($className)) ? "" : ' class="' . $className . '"';
		$output .= '<select multiple id="' . $this->setName . '"';
		$output .= ' name="' . $this->setName . '[]"' . $class . ">\n";
		foreach ($this->options as $optName =>$option) {
			$output .= '<option value="' . $option . '"';
			if (!empty($selected) && in_array($option,$selected)) {
				$output .= ' selected="selected"';
			}
			$output .= '>' . $optName . "</option>\n";
		}
		$output .= "</select>\n\n";
	}

	return $output;
}

function selected($selected=NULL) {
// return an associative array of selection option labels and values
	$output = array();
	if (!empty($selected)) {

		if ($this->type == 'option' OR $this->type == 'listbox') {

			foreach ($this->options as $optName =>$option) {
				if ($selected == $option) {
					$output[$optName] = $option;
					break;
				}
			}

		} else {
			foreach ($this->options as $optName =>$option) {
				if (in_array($option,$selected)) {
					$output[$optName] = $option;
				}
			}
		}
	}

	return $output;
}

}
?>