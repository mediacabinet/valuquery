<?php
namespace ValuQuery\DoctrineMongoOdm;

use Doctrine\ODM\MongoDB\Types\Type;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use ArrayAccess;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadataInfo;

class ValueConverter
{
    /**
     * Conversion mode: from database to PHP format
     * @var int
     */
    const CONVERT_TO_PHP = 1;
    
    /**
     * Conversion mode: from PHP to database format
     * @var int
     */
    const CONVERT_TO_DB = 2;
    
    /**
     * @var DocumentManager
     */
    protected $documentManager;

    public function __construct(DocumentManager $dm)
    {
        $this->documentManager = $dm;
    }
    
    /**
     * Retrieve current document manager instance
     *
     * @return DocumentManager
     */
    public function getDocumentManager()
    {
        return $this->documentManager;
    }

    /**
     * Convert value to database format
     * 
     * @param string $documentName
     * @param string $field
     * @param mixed $value
     */
    public function convertToDatabase($documentName, &$field, &$value)
    {
        return $this->splitFieldAndConvert($documentName, $field, $value, self::CONVERT_TO_DB);
    }
    
    /**
     * Split field and convert value
     * 
     * @param string $documentName
     * @param string $field
     * @param mixed $value
     * @param int $mode
     */
    protected function splitFieldAndConvert($documentName, &$field, &$value, $mode)
    {
        $fields = explode('.', $field);
        
        if (sizeof($fields) > 0) {
            $meta = $this->getDocumentManager()->getClassMetadata($documentName);
            
            foreach ($fields as $index => $fieldName) {
                
                if($index === (sizeof($fields)-1)) {
                    $this->convert($meta->name, $fieldName, $value, $mode);
                
                    $fields[$index] = $fieldName;
                } elseif($meta->hasAssociation($fieldName)) {
                    
                    // Map PHP attribute name to database field name
                    $fields[$index] = $this->mapField($meta, $fieldName, $mode);
                
                    $meta = $this->getDocumentManager()
                        ->getClassMetadata($meta->getAssociationTargetClass($fieldName));
                
                    if (!$meta) {
                        break;
                    }
                }
            }
            
            // Apply field name mapping
            $field = implode('.', $fields);
            
        } else {
            return $this->convert($documentName, $field, $value, $mode);
        }
    }

    /**
     * Convert value
     * 
     * @param string $documentName
     * @param string $field
     * @param mixed $value
     * @param int $mode
     */
    protected function convert($documentName, &$field, &$value, $mode)
    {
        $fieldName = $field;
        
        $meta = $this->getDocumentManager()->getClassMetadata($documentName);
        
        // Map field
        $field = $this->mapField($meta, $field, $mode);
        
        // Map _id automatically to correct identifier field name
        // (_id is mostly for internal usage)
        if ($fieldName === '_id') {
            $fieldName = $meta->getIdentifier();
        }
        
        // Do nothing if identifier and value is a boolean false
        if ($fieldName === $meta->getIdentifier() && $value === false) {
            return;
        }
        
        // Field is a discriminator field, treat it as a string
        if ($fieldName === $meta->discriminatorField) {
            $value = strval($value);
            return;
        }

        // Field is actually an association, treat it as an ID
        // based on target document's metadata
        if($meta->hasAssociation($fieldName)) {
            $fieldMapping = $meta->getFieldMapping($fieldName);
            $targetMeta = $this->getDocumentManager()
                ->getClassMetadata($fieldMapping['targetDocument']);

            $type = $this->resolveFieldType($targetMeta, $targetMeta->getIdentifier());

            // If we're using DBRefs, add .$db to field name
            if (!isset($fieldMapping['simple']) || $fieldMapping['simple'] !== true) {
                $field .= '.$id';
            }

            // Finally, convert ID to DB value
            if (is_array($value)) {
                foreach ($value as $key => &$id) {
                    $value[$key] = $type->convertToDatabaseValue($id);
                }
            } else {
                $value = $type->convertToDatabaseValue($value);
            }
            
            return;
        }

        // Find field type from the child class metadata
        $type = $this->resolveFieldType($meta, $fieldName);
        
        // Convert to DB value, unless we've found an association
        if ($type) {
            if (is_array($value)) {
                $value = array_map([$type, 'convertToDatabaseValue'], $value);
            } else {
                $value = $type->convertToDatabaseValue($value);
            }
        }
    }
    
    /**
     * Resolve field type
     * 
     * @param ClassMetadata $meta
     * @param string $field Field name
     * @return \Doctrine\ODM\MongoDB\Types\Type|null
     */
    private function resolveFieldType(ClassMetadata $meta, $field)
    {
        // Find field type from the child class metadata
        $type = $meta->getTypeOfField($field);
    
        // If field type was not defined in the class itself,
        // search for its parents until found
        if (!$type) {
            foreach ($meta->parentClasses as $class) {
                $type = $this->getDocumentManager()
                    ->getClassMetadata($class)
                    ->getTypeOfField($field);
    
                if ($type) {
                    break;
                }
            }
        }
    
        if ($type && $type !== 'collection' && $type !== 'one') {
            return Type::getType($type);
        } else {
            return null;
        }
    }
    
    /**
     * Map field to/from database field name
     * 
     * @param ClassMetadataInfo $meta
     * @param string $fieldName
     * @param int $mode
     * @return string Mapped field name
     */
    private function mapField(ClassMetadataInfo $meta, $fieldName, $mode)
    {
        if ($fieldName !== $meta->getIdentifier()) {
            if ($mode === self::CONVERT_TO_DB && isset($meta->fieldMappings[$fieldName])) {
                return $meta->fieldMappings[$fieldName]['name'];
            } else {
                foreach ($meta->fieldMappings as $phpFieldName => $specs) {
                    if (isset($specs['name']) && $specs['name'] === $fieldName) {
                        return $phpFieldName;
                    }
                }
            }
        }
        
        return $fieldName;
    }
}