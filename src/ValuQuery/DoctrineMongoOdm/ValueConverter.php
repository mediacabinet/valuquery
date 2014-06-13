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
     * @param string $field Name of the field
     * @param mixed $value Value
     */
    public function convertToDatabase($documentName, &$field, &$value)
    {
        return $this->splitFieldAndConvert($documentName, $field, $value, self::CONVERT_TO_DB);
    }
    
    /**
     * Convert array of DB values to PHP format
     * 
     * @param string $documentName
     * @param array $data DB data
     */
    public function convertArrayToPhp($documentName, array &$data)
    {
        $meta = $this->getDocumentManager()->getClassMetadata($documentName);
        
        foreach ($data as $fieldName => &$value) {
            $mappedField = $this->mapField($meta, $fieldName, self::CONVERT_TO_PHP);
            
            if($meta->hasAssociation($mappedField) && is_array($value)) {
            	try{
            		$targetDocument = $meta->getAssociationTargetClass($fieldName);
            	} catch(\Exception $e) {
            		$targetDocument = $meta->fieldMappings[$fieldName]['targetDocument'];
            	}
            	
                $this->convertArrayToPhp($targetDocument, $value);
            } else {
                $this->convert($documentName, $mappedField, $fieldName, $value, self::CONVERT_TO_PHP);
            }
            
            if ($mappedField !== $fieldName) {
                $data[$mappedField] = $data[$fieldName];
                unset($data[$fieldName]);
            }
        }
    }
    
    /**
     * Split field and convert value
     * 
     * @param string $documentName
     * @param string $field
     * @param mixed $value
     */
    protected function splitFieldAndConvert($documentName, &$field, &$value)
    {
        $fields = explode('.', $field);
        
        if (sizeof($fields) > 0) {
            $meta = $this->getDocumentManager()->getClassMetadata($documentName);
            
            foreach ($fields as $index => $fieldName) {
                
                $mappedField = $this->mapField($meta, $fieldName, self::CONVERT_TO_DB);
                
                if($index === (sizeof($fields)-1)) {
                    $this->convert($meta->name, $fieldName, $mappedField, $value, self::CONVERT_TO_DB);
                } elseif($meta->hasAssociation($fieldName)) {
                    
                    try{
                        $targetDocument = $meta->getAssociationTargetClass($fieldName);
                    } catch(\Exception $e) {
                        $targetDocument = $meta->fieldMappings[$fieldName]['targetDocument'];
                    }
                    
                    $meta = $this->getDocumentManager()
                        ->getClassMetadata($targetDocument);
                
                    if (!$meta) {
                        break;
                    }
                }
                
                $fields[$index] = $mappedField;
            }
            
            // Apply field name mapping
            $field = implode('.', $fields);
        } else {
            $mappedField = $this->mapField($meta, $fieldName, self::CONVERT_TO_DB);
            return $this->convert($documentName, $field, $mappedField, $value, self::CONVERT_TO_DB);
        }
    }

    /**
     * Convert value
     * 
     * @param string $documentName
     * @param string $phpField
     * @param string $field
     * @param mixed $value
     * @param int $mode
     */
    protected function convert($documentName, $phpField, &$field, &$value, $mode)
    {
        $meta = $this->getDocumentManager()->getClassMetadata($documentName);
        
        // Map _id automatically to correct identifier field name
        // (_id is mostly for internal usage)
        if ($phpField === '_id') {
            $phpField = $meta->getIdentifier();
        }
        
        // Do nothing if identifier and value is a boolean false
        if ($phpField === $meta->getIdentifier() && $value === false) {
            return;
        }
        
        // Field is a discriminator field, treat it as a string
        if ($phpField === $meta->discriminatorField) {
            $value = strval($value);
            return;
        }

        // Field is actually an association, treat it as an ID
        // based on target document's metadata
        if($meta->hasAssociation($phpField)) {
            $fieldMapping = $meta->getFieldMapping($phpField);
            $targetMeta = $this->getDocumentManager()
                ->getClassMetadata($fieldMapping['targetDocument']);

            $type = $this->resolveFieldType($targetMeta, $targetMeta->getIdentifier());

            // If we're using DBRefs, add .$db to field name
            if ($mode === self::CONVERT_TO_DB
                && (!isset($fieldMapping['simple']) || $fieldMapping['simple'] !== true)) {
                $field .= '.$id';
            }

            // Finally, convert to DB value
            $this->convertValue($type, $value, $mode);
            
            return;
        }

        // Find field type from the child class metadata
        $type = $this->resolveFieldType($meta, $phpField);
        
        // Convert to DB value, unless we've found an association
        if ($type) {
            $this->convertValue($type, $value, $mode);
        }
    }
    
    /**
     * Internal function for converting values to/from DB
     *
     * @param Type $type
     * @param mixed $value
     * @param int $mode
     */
    private function convertValue(Type $type, &$value, $mode) {
        if (is_array($value)) {
            if ($mode === self::CONVERT_TO_DB) {
                $value = array_map([$type, 'convertToDatabaseValue'], $value);
            } else {
                $value = array_map([$type, 'convertToPHPValue'], $value);
            }
        } else {
            if ($mode === self::CONVERT_TO_DB) {
                $value = $type->convertToDatabaseValue($value);
            } else {
                $value = $type->convertToPHPValue($value);
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
            if ($mode === self::CONVERT_TO_DB) {
                if (isset($meta->fieldMappings[$fieldName])) {
                    return $meta->fieldMappings[$fieldName]['name'];
                }
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