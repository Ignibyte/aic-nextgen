<?php

declare(strict_types=1);

namespace Gates\Application;

use InvalidArgumentException;
use stdClass;

use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_map;
use function get_object_vars;
use function is_array;
use function json_decode;

/**
 * Reads gate:23's script, `config/native-site.json` (`aic.native-site/1`), for the steps' reader and the socket
 * conversations' (#32).
 */
final readonly class NativeSiteDocument
{
    /**
     * @param QuietFileReader $quietFileReader Reads the file.
     */
    public function __construct(
        private QuietFileReader $quietFileReader = new QuietFileReader(),
    ) {
    }

    /**
     * @param string $root The project's root.
     *
     * @throws InvalidArgumentException When the script is not an `aic.native-site/1` object.
     *
     * @return stdClass The script, decoded.
     */
    public function read(string $root): stdClass
    {
        $decoded = [json_decode($this->quietFileReader->read($root . '/' . NativeSiteScript::PATH) ?? '')];

        return $decoded[0] instanceof stdClass
            && (get_object_vars($decoded[0])['schema'] ?? null) === 'aic.native-site/1'
            ? $decoded[0]
            : throw new InvalidArgumentException(NativeSiteScript::PATH . ' is not an aic.native-site/1 object.');
    }

    /**
     * @param string $root The project's root.
     * @param string $key  A list of objects in the script, such as `sockets`.
     *
     * @throws InvalidArgumentException When the script is malformed, or the key holds something other than a list.
     *
     * @return list<stdClass> Its entries, one that is not an object standing as an empty one; none when it is absent.
     */
    public function objects(string $root, string $key): array
    {
        $script = $this->read($root);

        return array_key_exists($key, get_object_vars($script))
            ? $this->entries($script, $key, NativeSiteScript::PATH)
            : [];
    }

    /**
     * @param stdClass $object An object of the script.
     * @param string   $key    One of its fields, a list of objects.
     * @param string   $where  The object, for the message.
     *
     * @throws InvalidArgumentException When the field is missing or not a list.
     *
     * @return list<stdClass> Its entries, one that is not an object standing as an empty one.
     */
    public function entries(stdClass $object, string $key, string $where): array
    {
        $fields = get_object_vars($object);
        $list = array_key_exists($key, $fields) && is_array($fields[$key]) && array_is_list($fields[$key])
            ? $fields[$key]
            : throw new InvalidArgumentException($where . ' holds no list at "' . $key . '".');

        return array_map(
            static fn (int $index): stdClass => $list[$index] instanceof stdClass ? $list[$index] : new stdClass(),
            array_keys($list),
        );
    }
}
