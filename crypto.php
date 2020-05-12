<?php

namespace todo\encryption;

class crypto {

  /**
   * @var string
   */
  private $nonce;

  /**
   * Method to construct instance of Crypto
   *
   * @param string $nonce Nonce to crypt string
   * @return void
   */
  public function __construct($nonce = '') {
    if (is_array($nonce)) {
      $this->nonce = $this->generateNonce();
    } else {
      $this->nonce = !empty($nonce) ? base64_decode($nonce) : $this->generateNonce();
    }
  }

  /**
   * Method to encode string
   *
   * @param string $key Key to encode
   * @param mixed $input Input to crypt
   * @return string
   */
  public function encode(string $key, string $input): string {
    $keyDecode = base64_decode($key);
    $keypair1_public = $this->getPublic($keyDecode);
    $keypair1_secret = $this->getSecret($keyDecode);
    return $this->_encode(serialize($input), $keypair1_public, $keypair1_secret);
  }

  /**
   * Method to decode string
   *
   * @param string $key Key to decode
   * @param string $input String to decode
   * @return mixed
   */
  public function decode(string $key, string $input) {
    $keyDecode = base64_decode($key);
    $keypair1_public = $this->getPublic($keyDecode);
    $keypair1_secret = $this->getSecret($keyDecode);
    return unserialize($this->_decode($input, $keypair1_public, $keypair1_secret));
  }

  /**
   * Method to generate new key
   *
   * @return string
   */
  public function generateKey(): string {
    return base64_encode(sodium_crypto_box_keypair());
  }

  /**
   * Method to generate new key
   *
   * @return string
   */
  public static function getKey(): string {
    return base64_encode(sodium_crypto_box_keypair());
  }

  /**
   * Method to generate new nonce
   *
   * @return string
   */
  public function generateNonce(): string {
    return random_bytes(SODIUM_CRYPTO_BOX_NONCEBYTES);
  }

  public function getNonce(): string {
    return base64_encode($this->nonce);
  }

  public function setNonce(string $nonce): void {
    $this->nonce = base64_decode($nonce);
  }

  /**
   * Method to decode string
   *
   * @param string $input Input to decode
   * @param string $keyPublic Key public
   * @param string $keySecret Key secret
   * @return string
   */
  private function _decode(string $input, string $keyPublic, string $keySecret) {
    $decryption_key = sodium_crypto_box_keypair_from_secretkey_and_publickey(base64_decode($keySecret), base64_decode($keyPublic));
    return sodium_crypto_box_open(base64_decode($input), $this->nonce, $decryption_key);
  }

  /**
   * Method to encode string
   *
   * @param string $input Input to encode
   * @param string $keyPublic Key public
   * @param string $keySecret Key secret
   * @return string
   */
  private function _encode(string $input, string $keyPublic, string $keySecret) {
    $encryption_key = sodium_crypto_box_keypair_from_secretkey_and_publickey(base64_decode($keySecret), base64_decode($keyPublic));
    return base64_encode(sodium_crypto_box($input, $this->nonce, $encryption_key));
  }

  /**
   * Method to return secret of key
   *
   * @param string $key
   * @return string
   */
  private function getSecret(string $key) {
    return base64_encode(sodium_crypto_box_secretkey($key));
  }

  /**
   * Method to return public of key
   *
   * @param string $key
   * @return string
   */
  private function getPublic(string $key) {
    return base64_encode(sodium_crypto_box_publickey($key));
  }
  
}
