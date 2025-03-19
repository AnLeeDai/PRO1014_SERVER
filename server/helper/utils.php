<?php

class Utils {
    public static function validateInput($data, $rules) {
        foreach ($rules as $key => $value) {
            if (empty($data[$key])) {
                http_response_code(400);
                die(json_encode(["success" => false, "message" => $value]));
            }
        }
    }

    public static function respond($data, $status = 200) {
        http_response_code($status);
        die(json_encode($data));
    }
}