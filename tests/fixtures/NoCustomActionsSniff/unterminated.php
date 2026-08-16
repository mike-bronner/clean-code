<?php

class DraftController
{
    public function publish(): RedirectResponse
    {
        return redirect()->route('drafts.index');
    }
