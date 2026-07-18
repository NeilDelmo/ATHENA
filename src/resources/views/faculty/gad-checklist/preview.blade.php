<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Box 7a - GAD Generic Checklist Preview</title>
        @vite('resources/css/gad-checklist-print.css')
    </head>
    <body class="gad-preview-page">
        <section class="gad-page gad-page-one" aria-label="Box 7a GAD Generic Checklist page 1">
            <p class="gad-project-title"><strong>Research Project Title:</strong> <strong>{{ $gadChecklist['project_title'] }}</strong></p>
            <p class="gad-assessment-title">Assessment of Gender-Responsiveness of Program/Project Design<sup>1</sup></p>
            <h1>Box 7a. Generic Checklist<sup>2</sup></h1>

            <table class="gad-table gad-score-table">
                <colgroup>
                    <col class="gad-element-column"><col class="gad-response-column"><col class="gad-response-column"><col class="gad-response-column"><col class="gad-score-column"><col class="gad-comments-column">
                </colgroup>
                <thead>
                    <tr><th rowspan="2">Element and item/question<br>(column 1)</th><th colspan="3">Response (column 2)</th><th rowspan="2">Score for an<br>item/element*<br>(column 3)</th><th rowspan="2">Gender issues<br>identified<br>(column 4)</th></tr>
                    <tr><th>No<br>(2a)</th><th>Partly<br>(2b)</th><th>Yes<br>(2c)</th></tr>
                </thead>
                <tbody>
                    <tr><td class="gad-element"><strong>1.0&nbsp;&nbsp;&nbsp;Involvement of women and men</strong><br><span>(max score: 2; 1 for each item)</span></td><td></td><td></td><td></td><td class="gad-thick-score">2.0</td><td></td></tr>
                    <tr><td>1.1 Participation of women and men in beneficiary groups in problem identification<br><span>(possible scores: 0, 0.5, 1.0)</span></td><td></td><td></td><td class="gad-mark">X</td><td>1.0</td><td></td></tr>
                    <tr><td>1.2 Participation of women and men in beneficiary groups in project design <span>(possible scores: 0, 0.5, 1.0)</span></td><td></td><td></td><td class="gad-mark">X</td><td>1.0</td><td></td></tr>
                    <tr><td class="gad-element"><strong>2.0 Collection of sex-disaggregated data and gender-related information</strong><br><span>(possible scores: 0, 1.0, 2.0)</span></td><td></td><td></td><td class="gad-mark">X</td><td class="gad-thick-score">2.0</td><td></td></tr>
                    <tr><td class="gad-element"><strong>3.0 Conduct of gender analysis and identification of gender issues</strong><br><span>(max score: 2; 1 for each item)</span></td><td></td><td></td><td></td><td class="gad-thick-score">1.0</td><td></td></tr>
                    <tr><td>3.1 Analysis of gender gaps and inequalities related to gender roles, perspectives and needs, or access to and control of resources<br><span>(possible scores: 0, 0.5, 1.0)</span></td><td></td><td class="gad-mark">X</td><td></td><td>0.5</td><td></td></tr>
                    <tr><td>3.2 Analysis of constraints and opportunities related to women and men&rsquo;s participation in the project<br><span>(possible scores: 0, 0.5, 1.0)</span></td><td></td><td class="gad-mark">X</td><td></td><td>0.5</td><td></td></tr>
                    <tr class="gad-total-row"><th>TOTAL GAD SCORE&mdash;<br>PROJECT IDENTIFICATION<br>STAGE</th><td colspan="3"></td><td class="gad-thick-score">5.0</td><td></td></tr>
                </tbody>
            </table>

            <div class="gad-footnotes">
                <p><sup>1</sup> This assessment tool applies to proposals. Terminal or accomplishment reports use a different checklist.</p>
                <p><sup>2</sup> Based on <em>Harmonized Gender and Development Guidelines (2016)</em> by Philippine Commission on Women, National Economic and Development Authority, Office Development Assistance Gender and Development Network</p>
            </div>
            <footer><strong>1</strong><span>Box 7a. GAD Checklist for Program/Project Design</span></footer>
        </section>

        <section class="gad-page gad-page-two" aria-label="Box 7a GAD Generic Checklist page 2">
            <table class="gad-table gad-score-table">
                <colgroup>
                    <col class="gad-element-column"><col class="gad-response-column"><col class="gad-response-column"><col class="gad-response-column"><col class="gad-score-column"><col class="gad-comments-column">
                </colgroup>
                <thead>
                    <tr><th rowspan="2">Element and guide questions<br>(column 1)</th><th colspan="3">Response (column 2)</th><th rowspan="2">Score for<br>item/<br>element*<br>(column 3)</th><th rowspan="2">Results or<br>comments<br>(column 4)</th></tr>
                    <tr><th>No<br>(2a)</th><th>Partly<br>(2b)</th><th>Yes<br>(2c)</th></tr>
                </thead>
                <tbody>
                    <tr><td class="gad-element"><strong>4.0 Gender equality goals, outcomes, and outputs</strong><br><span>(possible scores: 0, 1.0, 2.0)</span><br>Does the project have clearly stated gender equality goals, objectives, outcomes, or outputs?</td><td></td><td class="gad-mark">X</td><td></td><td class="gad-thick-score">1.0</td><td></td></tr>
                    <tr><td class="gad-element"><strong>5.0 Matching of strategies with gender issues</strong><br><span>(possible scores: 0, 1.0, 2.0)</span><br>Do the strategies and activities match the gender issues and gender equality goals identified?</td><td></td><td></td><td class="gad-mark">X</td><td class="gad-thick-score">2.0</td><td></td></tr>
                    <tr><td class="gad-element"><strong>6.0 Gender analysis of likely impacts of the project</strong><br><span>(max score: 2; for each item or question, 0.67)</span></td><td></td><td></td><td></td><td class="gad-thick-score">1.33</td><td></td></tr>
                    <tr><td>6.1 Are women and girl children among the direct or indirect beneficiaries?<br><span>(possible scores: 0, 0.33, 0.67)</span></td><td></td><td class="gad-mark">X</td><td></td><td>0.33</td><td></td></tr>
                    <tr><td>6.2 Has the project considered its long-term impact on women&rsquo;s socioeconomic status and empowerment?<br><span>(possible scores: 0, 0.33, 0.67)</span></td><td></td><td></td><td class="gad-mark">X</td><td>0.67</td><td></td></tr>
                    <tr><td>6.3 Has the project included strategies for avoiding or minimizing negative impact on women&rsquo;s status and welfare?<br><span>(possible scores: 0, 0.33, 0.67)</span></td><td></td><td class="gad-mark">X</td><td></td><td>0.33</td><td></td></tr>
                    <tr><td class="gad-element"><strong>7.0 Monitoring targets and indicators</strong><br><span>(possible scores: 0, 1.0, 2.0)</span><br>Does the project include gender equality targets and indicators to measure gender equality outputs and outcomes?</td><td></td><td class="gad-mark">X</td><td></td><td class="gad-thick-score">1.0</td><td></td></tr>
                    <tr><td class="gad-element"><strong>8.0 Sex-disaggregated database requirement</strong><br><span>(possible scores: 0, 1.0, 2.0)</span><br>Does the project monitoring and evaluation (M&amp;E) system require the collection of sex-disaggregated data?</td><td class="gad-mark">X</td><td></td><td></td><td class="gad-thick-score">0</td><td></td></tr>
                    <tr><td class="gad-element"><strong>9.0 Resources</strong> <span>(max score: 2; for each item or question, 1)</span></td><td></td><td></td><td></td><td class="gad-thick-score">1.0</td><td></td></tr>
                    <tr><td>9.1 Is the project&rsquo;s budget allotment sufficient for gender equality promotion or integration? OR, will the project tap counterpart funds from LGUs/partners for its GAD efforts?<br><span>(possible scores: 0, 0.5, 1.0)</span></td><td></td><td class="gad-mark">X</td><td></td><td>0.5</td><td></td></tr>
                    <tr><td>9.2 Does the project have the expertise to promote gender equality and women&rsquo;s empowerment? OR, is the project committing itself to invest project staff time in building capacities within the project to integrate GAD or promote gender equality?<br><span>(possible scores: 0, 0.5, 1.0)</span></td><td></td><td class="gad-mark">X</td><td></td><td>0.5</td><td></td></tr>
                </tbody>
            </table>
            <footer><strong>2</strong><span>Box 7a. GAD Checklist for Program/Project Design</span></footer>
        </section>

        <section class="gad-page gad-page-three" aria-label="Box 7a GAD Generic Checklist page 3">
            <table class="gad-table gad-score-table gad-score-table-final">
                <colgroup>
                    <col class="gad-element-column"><col class="gad-response-column"><col class="gad-response-column"><col class="gad-response-column"><col class="gad-score-column"><col class="gad-comments-column">
                </colgroup>
                <thead>
                    <tr><th rowspan="2">Element and guide questions<br>(Column 1)</th><th colspan="3">Response<br>(column 2)</th><th rowspan="2">Score for<br>item/<br>element*<br>(column 3)</th><th rowspan="2">Results or comments<br>(column 4)</th></tr>
                    <tr><th>No<br>(2a)</th><th>Partly<br>(2b)</th><th>No<br>(2a)</th></tr>
                </thead>
                <tbody>
                    <tr><td class="gad-element"><strong>10 Relationship with the agency&rsquo;s GAD efforts</strong><br><span>(max score: 2; for each question or item, 0.67)</span></td><td></td><td></td><td></td><td class="gad-thick-score">0.99</td><td></td></tr>
                    <tr><td>10.1 Will the project build on or strengthen the agency/NCRFW/government&rsquo;s commitment to the empowerment of women? <span>(possible scores: 0, 0.33, 0.67)</span><br>IF THE AGENCY HAS NO GAD PLAN: Will the project help towards the formulation of the implementing agency&rsquo;s GAD plan?</td><td></td><td class="gad-mark">X</td><td></td><td>0.33</td><td></td></tr>
                    <tr><td>10.2 Will it build on the initiatives or actions of other organizations in the area? <span>(possible scores: 0, 0.33, 0.67)</span></td><td></td><td class="gad-mark">X</td><td></td><td>0.33</td><td></td></tr>
                    <tr><td>10.3 Does the project have an exit plan that will ensure the sustainability of GAD efforts and benefits? <span>(possible scores: 0, 0.33, 0.67)</span></td><td></td><td class="gad-mark">X</td><td></td><td>0.33</td><td></td></tr>
                    <tr class="gad-total-row"><th colspan="4">TOTAL GAD SCORE FOR PROJECT DEVELOPMENT STAGE</th><td class="gad-thick-score">7.32</td><td></td></tr>
                    <tr class="gad-total-row"><th colspan="4">TOTAL GAD SCORE FOR THE PROJECT IDENTIFICATION<br>AND DESIGN STAGES</th><td class="gad-thick-score">12.32</td><td></td></tr>
                </tbody>
            </table>

            <table class="gad-table gad-interpretation-table">
                <thead><tr><th colspan="2">Interpretation of GAD Scores</th></tr></thead>
                <tbody>
                    <tr><td>0 - 3.9</td><td>GAD is invisible in the project (proposal is returned).</td></tr>
                    <tr><td>4.0 - 7.9</td><td>Proposed project <strong>has promising GAD prospects</strong> (proposal earns a &ldquo;conditional pass,&rdquo; pending identification of gender issues and strategies and activities to address these, and inclusion of the collection of sex-disaggregated data in the monitoring and evaluation plan).</td></tr>
                    <tr><td>8.0 -14.9</td><td>Proposed project is <strong>gender-sensitive</strong> (proposal passes the GAD test).</td></tr>
                    <tr><td>15.0 - 20.0</td><td>Proposed project is <strong>gender-responsive</strong> (proponent is commended).</td></tr>
                </tbody>
            </table>

            <h2 class="gad-budget-heading">Attribution to the GAD Budget</h2>
            <div class="gad-formula-item"><span>&bull;</span><em>HGDG Score/Total HGDG Score x 100% = % of annual program budget attributable to GAD</em></div>
            <div class="gad-fraction"><span>HGDG Score</span><span>20<br>(Total HGDG Score)</span><b>x 100</b></div>
            <div class="gad-formula-item"><span>&bull;</span><em>% of annual program budget attributable to GAD x annual program budget = attributable amount to GAD</em></div>

            <div class="gad-signatories">
                <div><p>Prepared by:</p><strong>{{ $gadChecklist['project_leader'] }}</strong><span>Project Leader</span></div>
                <div><p>Checked and verified by:</p><strong>{{ $gadChecklist['verifier_name'] }}</strong><span>{{ $gadChecklist['verifier_role'] }}</span></div>
            </div>
            <footer><strong>3</strong><span>Box 7a. GAD Checklist for Program/Project Design</span></footer>
        </section>

        <section class="gad-page gad-guide-page gad-page-four" aria-label="Box 7a GAD Generic Checklist page 4">
            <h2>Guide for accomplishing Box 7a</h2>
            <div class="gad-guide-row"><span>1.</span><p>Put a check (/) in the appropriate column (2a to 2c) under &ldquo;Response&rdquo; to signify the degree in which a project proponent has complied with the GAD element: under column 2a if nothing has been done; under column 2b if an element, item, or question has been partly complied with; and under column 2c if an element, item, or question has been fully complied with.</p></div>
            <div class="gad-guide-row"><span>2.</span><div><p>A partial and a full yes may be distinguished as follows.</p>
                <div class="gad-guide-subrow"><span>a.</span><p>For <em>Element 1.0</em>, a &ldquo;partly yes&rdquo; to Item 1.1 means meeting only with male officials and only a woman or a few women, who also happen to be officials in the proponent or partner agency or organization; or with male and female officials and some male beneficiaries. In contrast, full compliance involves meeting with female and male officials and consulting with other stakeholders, including women and men that could be affected positively or negatively by the proposed project. A &ldquo;partly yes&rdquo; to Item 1.2, on the other hand, means inputs or suggestions may have been sought from woman and man beneficiaries but are not considered at all in designing project activities and facilities.</p></div>
                <div class="gad-guide-subrow"><span>b.</span><p>For <em>Element 2.0</em>, &ldquo;partly yes&rdquo; means some information has been classified by sex but may not help identify key gender issues that a planned project must address. In contrast, a full &ldquo;yes&rdquo; implies that qualitative and quantitative data are cited in the analysis of the development issue or project.</p></div>
                <div class="gad-guide-subrow"><span>c.</span><p>For <em>Element 3.0</em>, a &ldquo;partly yes&rdquo; to Item 3.1 means a superficial or partial analysis has been done by focusing on only one or two of the concerns (gender roles, needs, perspectives, or access to and control of resources) while a &ldquo;partly yes&rdquo; to Item 3.2 means that an analysis of either constraints or opportunities, instead of both, or an analysis of constraints and opportunities only by women or by men, has been done.</p></div>
                <div class="gad-guide-subrow"><span>d.</span><p>For <em>Element 4.0</em>, &ldquo;partly yes&rdquo; means having a gender equality statement incorporated in any of the following levels: goal, purpose, or output. A full &ldquo;yes&rdquo; requires the integration of gender equality in at least two of the three levels.</p></div>
                <div class="gad-guide-subrow"><span>e.</span><p>For <em>Element 5.0</em>, &ldquo;partly yes&rdquo; means having gender equality strategies or activities, but no stated gender issues that will match the activities, while a full &ldquo;yes&rdquo; requires an identified gender issue and activities that seek to address this issue.</p></div>
                <div class="gad-guide-subrow"><span>f.</span><p>For <em>Element 6.0</em>, a &ldquo;partly yes&rdquo; to Item 6.1 means women or girls comprise less than a third of the project&rsquo;s indirect or direct beneficiaries; to Item 6.2, it means the project focuses on affecting socioeconomic status with no consideration to women&rsquo;s empowerment; and to Item 6.3 means mitigating strategies deal only with minimizing negative impact on welfare, with no regard for status. A full &ldquo;yes&rdquo; to an item under Element 6.0 means women or girls constitute at least a third of the project beneficiaries (Item 6.1), the project will impact on both material condition and status (Item 6.2), and the project seeks to minimize negative impact on women&rsquo;s status as well as welfare (Item 6.3).</p></div>
                <div class="gad-guide-subrow"><span>g.</span><p>For <em>Element 7.0</em>, &ldquo;partly yes&rdquo; means the project monitoring plan includes indicators that are sex-disaggregated, with no qualitative indicator of empowerment or status change.</p></div>
                <div class="gad-guide-subrow"><span>h.</span><p>For <em>Element 8.0</em>, &ldquo;partly yes&rdquo; means the project requires the collection of some sex-disaggregated data or information, but not all the information that will track the gender-differentiated effects of the project. A full &ldquo;yes&rdquo; means all sex-disaggregated data and qualitative information will be collected to help monitor the GAD outcomes and outputs.</p></div>
                <div class="gad-guide-subrow"><span>i.</span><p>For <em>Element 9.0</em>, &ldquo;partly yes&rdquo; means there is a budget for GAD-related activities but not sufficient to ensure that the project will address relevant gender issues (Item 9.1), or to build GAD capacities among project staff or the project agency, or to tap external GAD expertise (Item 9.2).</p></div>
                <div class="gad-guide-subrow"><span>j.</span><p>For <em>Element 10.0</em>, a &ldquo;partly yes&rdquo; to Item 10.1 means there is a mention of the agency&rsquo;s GAD plan but no direct connection is made to incorporate the project&rsquo;s GAD efforts into the plan; to Item 10.2 means there is a mention of other GAD initiatives in the project coverage but no indication of how the project will build on these initiatives; and to Item 10.3 means the project has a sustainability plan for its GAD efforts but no mention is made of how these may be institutionalized within the implementing agency or its partners.</p></div>
            </div></div>
            <div class="gad-guide-row"><span>3.</span><div><p>Enter the appropriate score for an element or item under column 3.</p>
                <div class="gad-guide-subrow"><span>a.</span><p>To ascertain the score for a GAD element, a three-point rating scale is provided: &ldquo;0&rdquo; when the proponent has not accomplished any of the activities or questions listed under an element or requirement; a score that is less than the stated maximum when compliance is only partial; and &ldquo;2&rdquo; (for the element or requirement), or the maximum score for an item or question, when the proponent has done all the required activities.</p></div>
            </div></div>
            <footer><strong>4</strong><span>Box 7a. GAD Checklist for Program/Project Design</span></footer>
        </section>

        <section class="gad-page gad-guide-page gad-page-five" aria-label="Box 7a GAD Generic Checklist page 5">
            <div class="gad-guide-continuation">
                <div class="gad-guide-subrow"><span>b.</span><p>The scores for &ldquo;partly yes&rdquo; differ by element. For instance, the score for &ldquo;partly yes&rdquo; for sex-disaggregated data in project identification and planning (Element 2.0) is &ldquo;1.&rdquo; For elements that have two or more items or questions (such as Elements 1.0 and 3.0), the rating for a &ldquo;partial yes&rdquo; is the sum of the scores of the items or questions that falls short of the maximum &ldquo;2.0.&rdquo; For instance, the score for &ldquo;partly yes&rdquo; for Elements 4.0, 5.0, 7.0, and 8.0 is &ldquo;1.&rdquo; For elements that have two or more items or questions (such as Element 6.0), the rating for a &ldquo;partial yes&rdquo; is the sum of the scores of the items or questions which falls short of the maximum &ldquo;2.0.&rdquo;</p></div>
                <div class="gad-guide-subrow"><span>c.</span><p>Because Elements 1.0 and 3.0 have been broken down into two items each, the maximum point (full &ldquo;yes&rdquo;) for each item is pegged at &ldquo;1.0&rdquo; and that for &ldquo;partly yes&rdquo; is &ldquo;0.5.&rdquo; The score for the element will be a positive number that is lower than &ldquo;2.0,&rdquo; the maximum score for the element.</p></div>
                <div class="gad-guide-subrow"><span>d.</span><p>For Element 9.0, which has two items (9.1 and 9.2), the maximum score for each item is pegged at &ldquo;1.0&rdquo; and for &ldquo;partly yes&rdquo; is &ldquo;0.5.&rdquo; Hence, if a project scores a full &ldquo;1.0&rdquo; in one question but &ldquo;0&rdquo; in the other, or if a project scores &ldquo;partly yes&rdquo; (or &ldquo;0.5&rdquo;) in each of the two items, the total rating for Element 9.0 would be &ldquo;partly yes&rdquo; with a score of &ldquo;1.0.&rdquo; If a project scores &ldquo;partly yes&rdquo; (&ldquo;0.5&rdquo;) in one item but no (&ldquo;0&rdquo;) in the other, the total rating for the element will be &ldquo;0.5.&rdquo;</p></div>
                <div class="gad-guide-subrow"><span>e.</span><p>For Elements 6.0 and 10.0, which have three items each, the maximum score for each item is pegged at &ldquo;0.67&rdquo; and for &ldquo;partly yes&rdquo; is &ldquo;0.33.&rdquo; The rating for the element will be &ldquo;partly yes&rdquo; if the total score of the three items is positive but less than &ldquo;2.0,&rdquo; the maximum for the element.</p></div>
            </div>
            <div class="gad-guide-row"><span>4.</span><p>For an element (column 1) that has more than one item or question, add the score for the items and enter the sum in the thickly bordered cell for the element.</p></div>
            <div class="gad-guide-row"><span>5.</span><p>Add the scores in the thickly bordered cells under column 3 to come up with the GAD score for the project identification stage.</p></div>
            <div class="gad-guide-row"><span>6.</span><p>Under the last column, indicate the key gender issues identified (for proponents) or comments on the proponent&rsquo;s compliance with the requirement (for evaluators).</p></div>

            <table class="gad-table gad-requirements-table gad-requirements-heading">
                <thead><tr><th>Elements/<br>Requirements</th><th>Things to Consider</th><th>Notes</th></tr></thead>
            </table>
            <footer><strong>5</strong><span>Box 7a. GAD Checklist for Program/Project Design</span></footer>
        </section>

        <section class="gad-page gad-requirements-page" aria-label="Box 7a GAD Generic Checklist page 6">
            <table class="gad-table gad-requirements-table">
                <colgroup><col class="gad-requirement-column"><col class="gad-consideration-column"><col class="gad-notes-column"></colgroup>
                <tbody>
                    <tr><td><span class="gad-row-number">1.</span>Participation of women and men in project identification of the development problem and design</td><td><ul><li>Consult with women and men beneficiaries at the earliest stage of the project</li><li>This will ensure that their concerns are taken into consideration</li></ul></td><td><ul><li>Inputs to project design</li></ul></td></tr>
                    <tr><td><span class="gad-row-number">2.</span>Collection and use of sex-disaggregated data in the analysis of the development problem</td><td><ul><li>Sex-disaggregated data and gender-related information are necessary inputs to a comprehensive analysis of the situation</li><li>Primary or secondary data can be used<ul class="gad-check-list"><li>Who are the intended project beneficiaries? How many are women, men, children?</li><li>What are their profiles? Are they housewives? with livelihood? working husbands? school age children?</li></ul></li></ul></td><td><ul><li>Data available for identifying gender issues</li></ul></td></tr>
                    <tr><td><span class="gad-row-number">3.</span>Conduct of gender analysis to identify the gender issues that the proposed project should address</td><td><ul><li>Sample basic questions to ask:<ul class="gad-check-list"><li>What gender issue will the project address?</li><li>What is the cause of the gender issue?</li><li>What resources are available to women and men beneficiaries?</li><li>What resources do women have control over?</li><li>Who has control over the benefits derived from it?</li><li>What are the pervading beliefs in the community that affects or limits the participation of men and women in the project?</li><li>How should the project be designed so it becomes responsive to women?</li></ul></li></ul></td><td><ul><li>Gender issues identified before project design</li></ul></td></tr>
                    <tr><td><span class="gad-row-number">4.</span>Goals and objectives, outcomes and outputs include GAD statements that address the gender issues in # 3</td><td><ul><li>Project goals/objectives which contributes to the following:<ol><li>increased economic empowerment of women</li><li>protection and fulfilment of women&rsquo;s human rights</li><li>gender-responsive governance</li></ol></li></ul></td><td><ul><li>Articulation of project goals or objectives, activities, analysis of likely gender impact of the project, monitoring targets and indicators, and sex-disaggregated data requirement</li></ul></td></tr>
                    <tr><td><span class="gad-row-number">5.</span>Activities include those that address the identified gender issues, including constraints to women&rsquo;s participation</td><td><ul><li>Activities that will address gender issues identified in # 3</li></ul></td><td><ul><li>Articulation of project goals or objectives, activities, analysis of likely gender impact of the project, monitoring targets and indicators, and sex-disaggregated data requirement</li></ul></td></tr>
                </tbody>
            </table>
            <footer><strong>6</strong><span>Box 7a. GAD Checklist for Program/Project Design</span></footer>
        </section>

        <section class="gad-page gad-requirements-page" aria-label="Box 7a GAD Generic Checklist page 7">
            <table class="gad-table gad-requirements-table">
                <colgroup><col class="gad-requirement-column"><col class="gad-consideration-column"><col class="gad-notes-column"></colgroup>
                <tbody>
                    <tr><td><span class="gad-row-number">6.</span>Conduct of gender analysis of the planned project to anticipate gender-related issues arising from the implementation of the designed project</td><td><ul><li>Gender analysis of the planned project:<ul class="gad-check-list"><li>What practical gender needs are responded to by the project? strategic gender needs?</li><li>Who decides to use or dispose of the resource, service or facilities?</li><li>Are there gender gaps in the access/use/management of resources? What are these?</li><li>Will the project reduce gender gaps between women and men? How?</li><li>Will the project mitigate constraints and promote women and men participation in project activities and benefits? How?</li><li>Will it improve the status of women? How?</li></ul></li></ul></td><td><ul><li>Articulation of project goals or objectives, activities, analysis of likely gender impact of the project, monitoring targets and indicators, and sex-disaggregated data requirement</li></ul></td></tr>
                    <tr><td><span class="gad-row-number">7.</span>Monitoring indicators and targets which include the reduction of gender gaps or improvement of women&rsquo;s participation</td><td><ul><li>Sample indicators and targets:<ul class="gad-check-list"><li>30% increase of women project beneficiaries participating in the management of water supply system</li><li>10% increase in women adopting FP methods</li><li>30% increase in women participation in barangay disaster risk response teams</li></ul></li></ul></td><td><ul><li>Articulation of project goals or objectives, activities, analysis of likely gender impact of the project, monitoring targets and indicators, and sex-disaggregated data requirement</li></ul></td></tr>
                    <tr><td><span class="gad-row-number">8.</span>Project monitoring and evaluation system that includes sex-disaggregated database</td><td><ul><li>Monitoring and reports should be sex-disaggregated</li></ul></td><td><ul><li>Articulation of project goals or objectives, activities, analysis of likely gender impact of the project, monitoring targets and indicators, and sex-disaggregated data requirement</li></ul></td></tr>
                    <tr><td><span class="gad-row-number">9.</span>Resources and budgets for the activities in # 5</td><td><ul><li>There should be budget allocation provided for the planned activities that will address the gender issues in #5 and facilitate integration of GAD in the project</li></ul></td><td><ul><li>Budget allocation to promote gender equality and women&rsquo;s empowerment</li></ul></td></tr>
                    <tr><td><span class="gad-row-number">10.</span>Planned coordination with PCW or the agency&rsquo;s GAD plans</td><td><ul><li>Proposed project should be in line with the agency&rsquo;s GAD efforts</li></ul></td><td><ul><li>Indication of coherence of the project&rsquo;s GAD plan with the agency&rsquo;s</li></ul></td></tr>
                </tbody>
            </table>
            <footer><strong>7</strong><span>Box 7a. GAD Checklist for Program/Project Design</span></footer>
        </section>
    </body>
</html>
