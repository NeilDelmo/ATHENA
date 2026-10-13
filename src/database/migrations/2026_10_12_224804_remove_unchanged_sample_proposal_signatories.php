<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $samples = [
            ['checked_verified_by_name', 'Dr. Maria Elena Santos', 'Director, Research Development and Innovation'],
            ['checked_verified_by_name', 'Dr. Victor A. Ramos', 'Research Center Director'],
            ['recommending_approval_name', 'Dr. Rafael D. Mendoza', 'Vice Chancellor for Research, Development and Extension'],
            ['recommending_approval_name', 'Dr. Celeste M. Flores', 'Vice President for Research and Innovation'],
            ['approved_by_name', 'Dr. Patricia Anne Villanueva', 'University President'],
            ['approved_by_name', 'Dr. Lorenzo B. Castillo', 'University Chancellor'],
            ['verified_by', 'Dr. Liza M. Navarro', 'Head, Research and Extension'],
            ['verified_by', 'Prof. Adrian C. Mercado', 'College Research Coordinator'],
            ['certified_by', 'Maria Teresa R. Cruz, CPA', 'University Accountant'],
            ['certified_by', 'Daniel P. Soriano, CPA', 'Chief Budget Officer'],
            ['verifier_name', 'Prof. Angela Mae de Leon', 'GAD Focal Person'],
            ['verifier_name', 'Dr. Irene C. Valdez', 'Chair, GAD Committee'],
            ['screening_head', 'Dr. Jose Miguel Aquino', 'Head, Research and Extension'],
            ['screening_head', 'Dr. Nina P. Robles', 'Research Office Head'],
            ['screening_center', 'Dr. Camille R. Bautista', 'Center Head'],
            ['screening_center', 'Engr. Marco Luis Herrera', 'Assistant Director, Research Center'],
            ['screening_verifier', 'Dr. Roberto S. Lim', 'Director of Research'],
            ['screening_verifier', 'Dr. Sofia T. Evangelista', 'Vice Chancellor for Research'],
        ];

        foreach ($samples as [$roleKey, $name, $position]) {
            DB::table('proposal_signatories')
                ->where('role_key', $roleKey)
                ->where('name', $name)
                ->where('position', $position)
                ->where('active', true)
                ->where('is_default', false)
                ->whereColumn('created_at', 'updated_at')
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: restoring demo entries would repopulate the institutional directory.
    }
};
