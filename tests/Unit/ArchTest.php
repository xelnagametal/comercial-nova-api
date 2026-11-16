<?php

arch()->preset()->php();

arch()->preset()->security();

arch('controllers extend the base controller')
    ->expect('App\Http\Controllers')
    ->toExtend('App\Http\Controllers\Controller');
