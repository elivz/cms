<?php
namespace Craft;

/**
 * Tags fieldtype
 */
class TagsFieldType extends BaseElementFieldType
{
	private $_tagGroupId;

	/**
	 * @access protected
	 * @var string $elementType The element type this field deals with.
	 */
	protected $elementType = 'Tag';

	/**
	 * @access protected
	 * @var bool $allowMultipleSources Whether the field settings should allow multiple sources to be selected.
	 */
	protected $allowMultipleSources = false;

	/**
	 * @access protected
	 * @var bool $allowLimit Whether to allow the Limit setting.
	 */
	protected $allowLimit = false;

	/**
	 * Returns the field's input HTML.
	 *
	 * @param string $name
	 * @param mixed  $criteria
	 * @return string
	 */
	public function getInputHtml($name, $criteria)
	{
		if (!($criteria instanceof ElementCriteriaModel))
		{
			$criteria = craft()->elements->getCriteria($this->elementType);
			$criteria->id = false;
		}

		$elementVariable = new ElementTypeVariable($this->getElementType());

		$tagGroup = $this->_getTagGroup();

		if ($tagGroup)
		{
			return craft()->templates->render('_components/fieldtypes/Tags/input', array(
				'elementType'     => $elementVariable,
				'id'              => craft()->templates->formatInputId($name),
				'name'            => $name,
				'elements'        => $criteria,
				'tagGroupId'      => $this->_getTagGroupId(),
				'sourceElementId' => (isset($this->element->id) ? $this->element->id : null),
				'hasFields'       => (bool) $tagGroup->getFieldLayout()->getFields(),
			));
		}
		else
		{
			return '<p class="error">'.Craft::t('This field is not set to a valid source.').'</p>';
		}
	}

	/**
	 * Performs any additional actions after the element has been saved.
	 */
	public function onAfterElementSave()
	{
		$tagGroupId = $this->_getTagGroupId();

		if ($tagGroupId === false)
		{
			return;
		}

		$rawValue = $this->element->getContent()->getAttribute($this->model->handle);

		if ($rawValue !== null)
		{
			$tagIds = is_array($rawValue) ? array_filter($rawValue) : array();

			foreach ($tagIds as $i => $tagId)
			{
				if (strncmp($tagId, 'new:', 4) == 0)
				{
					$name = mb_substr($tagId, 4);

					// Last-minute check
					$criteria = craft()->elements->getCriteria(ElementType::Tag);
					$criteria->groupId = $tagGroupId;
					$criteria->search = 'name::'.$name;
					$ids = $criteria->ids();

					if ($ids)
					{
						$tagIds[$i] = $ids[0];
					}
					else
					{
						$tag = new TagModel();
						$tag->groupId = $tagGroupId;
						$tag->name = $name;

						if (craft()->tags->saveTag($tag))
						{
							$tagIds[$i] = $tag->id;
						}
					}
				}
			}

			craft()->relations->saveRelations($this->model->id, $this->element->id, $tagIds);
		}
	}

	/**
	 * Returns the tag group associated with this field.
	 *
	 * @access private
	 * @return TagGroupModel|null
	 */
	private function _getTagGroup()
	{
		$tagGroupId = $this->_getTagGroupId();

		if ($tagGroupId)
		{
			return craft()->tags->getTagGroupById($tagGroupId);
		}
	}

	/**
	 * Returns the tag group ID this field is associated with.
	 *
	 * @access private
	 * @return int|false
	 */
	private function _getTagGroupId()
	{
		if (!isset($this->_tagGroupId))
		{
			$source = $this->getSettings()->source;

			if (strncmp($source, 'taggroup:', 9) == 0)
			{
				$this->_tagGroupId = (int) mb_substr($source, 9);
			}
			else
			{
				$this->_tagGroupId = false;
			}
		}

		return $this->_tagGroupId;
	}
}
