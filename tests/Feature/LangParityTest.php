<?php

namespace Tests\Feature;

use Tests\TestCase;

/** id.json dan en.json harus memiliki kunci yang sama (paritas terjemahan). */
class LangParityTest extends TestCase
{
    public function test_kunci_id_en_paritas(): void
    {
        $id = json_decode((string) file_get_contents(lang_path('id.json')), true);
        $en = json_decode((string) file_get_contents(lang_path('en.json')), true);

        $this->assertIsArray($id);
        $this->assertIsArray($en);
        $this->assertSame([], array_diff_key($id, $en), 'Kunci hilang di en.json');
        $this->assertSame([], array_diff_key($en, $id), 'Kunci hilang di id.json');
    }
}
