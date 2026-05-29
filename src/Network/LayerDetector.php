<?php

namespace XnoxsProto\Network;

use XnoxsProto\TL\Functions\HelpGetNearestDcRequest;
use XnoxsProto\TL\Functions\InvokeWithLayerRequest;
use XnoxsProto\TL\Functions\InitConnectionRequest;
use XnoxsProto\TL\BinaryReader;
use XnoxsProto\Exceptions\RPCException;

/**
 * LayerDetector — auto-detects the highest MTProto API layer supported by Telegram.
 *
 * Strategy:
 *   1. Try the preferred (latest) layer first.
 *   2. If the server responds with an unknown constructor or RPC error, fall back
 *      through a list of known-good layers until one works.
 *   3. Cache the result in the session so detection runs only once per session.
 *
 * The detection call is help.getNearestDc — the lightest possible request that
 * works at any layer and does not require authentication.
 */
class LayerDetector
{
    /**
     * Ordered list of layers to try, from newest to oldest.
     * Add new layers at the front as Telegram releases updates.
     */
    private const CANDIDATE_LAYERS = [214, 181, 176, 166, 160, 147, 135];

    /** Constructor for nearestDc#8e1a1775 */
    private const NEAREST_DC_CTOR = 0x8e1a1775;

    /**
     * Detect and return the highest working layer.
     *
     * @param MTProtoSender $sender   Ready-to-use encrypted sender
     * @param int           $apiId    API ID for InitConnection wrapper
     * @param int           $preferred Preferred layer to try first (default: latest)
     * @return array{layer:int, this_dc:int, nearest_dc:int, country:string}
     */
    public static function detect(
        MTProtoSender $sender,
        int $apiId,
        int $preferred = 214
    ): array {
        // Build candidate list: preferred first, then fall-backs in descending order
        $candidates = array_unique(array_merge([$preferred], self::CANDIDATE_LAYERS));

        foreach ($candidates as $layer) {
            try {
                $result = self::tryLayer($sender, $apiId, $layer);
                if ($result !== null) {
                    return $result;
                }
            } catch (RPCException $e) {
                // Some RPC errors are fatal (auth issues, flood wait) — re-throw
                if (in_array($e->getCode(), [420, 401], true)) {
                    throw $e;
                }
                // Otherwise, try the next layer
                continue;
            } catch (\RuntimeException $e) {
                continue;
            }
        }

        // All layers failed — return a safe default so the client still works
        return [
            'layer'      => self::CANDIDATE_LAYERS[count(self::CANDIDATE_LAYERS) - 1],
            'this_dc'    => 2,
            'nearest_dc' => 2,
            'country'    => 'unknown',
        ];
    }

    /**
     * Attempt help.getNearestDc with a specific layer.
     * Returns parsed result array on success, null if constructor is unrecognized.
     */
    private static function tryLayer(MTProtoSender $sender, int $apiId, int $layer): ?array
    {
        $request = new InvokeWithLayerRequest(
            $layer,
            new InitConnectionRequest(
                $apiId,
                'XnoxsProto',
                php_uname('s') . ' ' . php_uname('r'),
                '1.0.0',
                'en', '', 'en',
                new HelpGetNearestDcRequest()
            )
        );

        $response = $sender->send($request);
        $ctor     = $response['constructor'];

        if ($ctor !== self::NEAREST_DC_CTOR) {
            // Unknown constructor — this layer may be too new or too old
            return null;
        }

        /** @var BinaryReader $r */
        $r = $response['reader'];

        return [
            'layer'      => $layer,
            'country'    => $r->readString(),
            'this_dc'    => $r->readInt(),
            'nearest_dc' => $r->readInt(),
        ];
    }
}
