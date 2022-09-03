<?php
// Create the authentication class
class Authorisation {
    public $LoginCode = null;           // Mandatory
    public $AuthorisationKey = null;    // Mandatory
}

// Create the deliveryinformation class
class DeliveryInformation {
    public $DeliveryGLN = null;
    public $CustomerEmail = null;    
    public $CompanyName = null;
    public $FirstName = null;          // Mandatory
    public $Infix = null;
    public $LastName = null;           // Mandatory
    public $Street = null;             // Mandatory
    public $HouseNr = null;            // Mandatory
    public $HouseAdd = null;
    public $City = null;               // Mandatory
    public $PostalCode = null;         // Mandatory
    public $CountryCode = null;
}

// Create the orderline class
class OrderLine {
    public $ProductCode = null;         // Mandatory
    public $ProductDescription = null;  // Only used in case of product_code incorrect to give complete exception 
    public $OrderQuantity = null;       // Mandatory
    public $OrderComment = null;
}

// Create the order class
class Order {
    public $OrderReference = null;
    public $OrderComments = null;
    public $EmailAddress = null;
    public $PhoneNumber = null;
    public $MobileNumber = null;
    public $DeliveryDate = null;
    public $DeliveryMethod = null;
    public $OrderLines = null;          // Mandatory
    public $DeliveryInformation = null;
}