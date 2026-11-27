<?php
/** Request HTTP umum: membaca input, tanpa aturan pengguna atau respons JSON. */
class FormatRequestTidakDidukung extends RuntimeException
{
}

class Request
{
    private bool $sudahDibaca = false;
    private array $data = [];
    private array $berkas = [];

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Semua field body, tidak termasuk query URL maupun file. */
    public function all(): array
    {
        $this->bacaBody();
        return $this->data;
    }

    public function input(string $nama, mixed $default = null): mixed
    {
        return $this->all()[$nama] ?? $default;
    }

    public function query(string $nama, mixed $default = null): mixed
    {
        return $_GET[$nama] ?? $default;
    }

    public function files(): array
    {
        $this->bacaBody();
        return $this->berkas;
    }

    public function file(string $nama): ?array
    {
        $file = $this->files()[$nama] ?? null;
        return ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE ? null : $file;
    }

    /** Body dibaca sekali agar all(), input(), dan file() bisa dipanggil bergantian. */
    private function bacaBody(): void
    {
        if ($this->sudahDibaca) {
            return;
        }
        $tipe = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
        if (in_array($this->method(), ['GET', 'HEAD'], true)) {
            $this->data = [];
        } elseif ($tipe === 'application/json') {
            $objek = json_decode(file_get_contents('php://input'));
            if (json_last_error() !== JSON_ERROR_NONE || !$objek instanceof stdClass) {
                throw new InvalidArgumentException('Body harus objek JSON yang valid.');
            }
            $this->data = (array) $objek;
        } elseif (in_array($tipe, ['multipart/form-data', 'application/x-www-form-urlencoded'], true)) {
            if ($this->method() === 'POST') {
                $this->data = $_POST;
                $this->berkas = $_FILES;
            } elseif ($tipe === 'application/x-www-form-urlencoded') {
                parse_str(file_get_contents('php://input'), $this->data);
            } else {
                if (!function_exists('request_parse_body')) {
                    throw new FormatRequestTidakDidukung('Multipart selain POST memerlukan PHP 8.4+. Gunakan POST dengan _method=PUT untuk update pengguna.');
                }
                try {
                    [$this->data, $this->berkas] = request_parse_body();
                } catch (RequestParseBodyException $exception) {
                    throw new InvalidArgumentException('Form-data tidak valid atau melebihi batas upload PHP.');
                }
            }
        } else {
            throw new FormatRequestTidakDidukung('Gunakan JSON, form-data, atau x-www-form-urlencoded.');
        }
        $this->sudahDibaca = true;
    }
}
