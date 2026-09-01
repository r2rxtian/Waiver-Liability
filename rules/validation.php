<?php
declare(strict_types=1);

function waiver_type_for(string $assessment,string $decision): ?string
{
    if($assessment==='UNFIT_TO_WORK'&&$decision==='CONTINUE_WORKING') return 'UNFIT_TO_WORK';
    if(in_array($decision,['REFUSED_MEDICAL_TREATMENT','REFUSED_HOSPITAL_EVALUATION','REFUSED_TRANSPORT'],true)) return 'REFUSED_MEDICAL_TREATMENT';
    return null;
}

function acknowledgments(string $type): array
{
    return $type==='UNFIT_TO_WORK' ? [
        'The recommendation was explained to me.',
        'I understand that I have been advised that I am unfit to work.',
        'I understand that continuing to work is against the recommendation provided.',
        'I voluntarily choose to continue working.',
        'I have read and understood this waiver.',
    ] : [
        'I understand that additional medical attention was recommended.',
        'The recommendation was explained to me.',
        'I understand that transportation was offered when applicable.',
        'I voluntarily refuse the recommended medical action.',
        'I understand the possible consequences explained to me.',
        'I accept responsibility for my decision.',
        'I have read and understood this waiver.',
    ];
}
