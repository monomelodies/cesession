<?php

namespace Cesession;

interface Handler
{
    public function read($id);
    public function write($id, $data);
    public function destroy($id);
    public function gc();
}

