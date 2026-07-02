<?php

namespace App\Enums;

/**
 * Direction of travel for a transport line.
 *
 * A real-world bus line (e.g. "Línea 1") generates two records in
 * the `lines` table: one OUTBOUND ("ida") and one RETURN ("vuelta").
 * They share the same `code` and are linked via `parent_line_id`.
 * Circular or single-sense lines have only one record and no counterpart.
 */
enum LineSense: string
{
    case Outbound = 'OUTBOUND';
    case Return = 'RETURN';
}
