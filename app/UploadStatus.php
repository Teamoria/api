<?php

namespace App;

enum UploadStatus: string
{
    case PENDING = 'pending';
    case UPLOADING = 'uploading';
    case SUCCESS = 'success';
    case FAILED = 'failed';

}
