<?php

/*
    This defines the interface for a raven compatible system, by abstracting 
    this it makes it easy to dropin replacement WLSs (e.g. demo server)
*/

interface RavenAuthServiceInterface {
	public function getURL();

	public function getCertificate($kid);
}