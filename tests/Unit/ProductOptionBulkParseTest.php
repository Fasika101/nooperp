<?php

namespace Tests\Unit;

use App\Models\ProductOption;
use PHPUnit\Framework\TestCase;

class ProductOptionBulkParseTest extends TestCase
{
    public function test_parse_bulk_names_splits_newlines_and_commas_and_dedupes_case_insensitively(): void
    {
        $raw = "Red, Blue\nblue\nGreen, Yellow";

        $names = ProductOption::parseBulkNames($raw);

        $this->assertSame(['Red', 'Blue', 'Green', 'Yellow'], $names);
    }
}
