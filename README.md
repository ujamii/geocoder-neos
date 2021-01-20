# Geocoder Mixin for NEOS CMS

[![Packagist](https://img.shields.io/packagist/v/ujamii/geocoder-neos.svg?colorB=green&style=flat)](https://packagist.org/packages/ujamii/geocoder-neos)

This package provides a very flexible way of geocoding your node properties.
Imagine you have a document or content type with some physical address data (street, zip code, city) and
you want to display this data on a map (for instance with [WebExcess.OpenStreetMap](https://github.com/webexcess/openstreetmap))
or you need the geo data for some calculations.

## Installation

```shell
composer req ujamii/geocoder-neos php-http/guzzle6-adapter
```

## Usage

The package provides a new node type mixin "Ujamii.Geocoder:Mixin.AddressData". Add this mixin to the desired NodeType in your package:

```yaml
'Your.Package:Document.BranchLocation':
    superTypes:
        'Neos.Neos:Document': true
        'Ujamii.Geocoder:Mixin.AddressData': true
```

This will add some new properties to the node inspector. When you add address data there, the geodata
will be added after you safe your changes. This is done by a pretty [feature rich package](https://github.com/geocoder-php/Geocoder)

> Where is the difference to [FormatD.GeoIndexable](https://github.com/Format-D/FormatD.GeoIndexable) or [Wwwision.Neos.AddressEditor](https://github.com/bwaidelich/Wwwision.Neos.AddressEditor)?

you may ask. Well, tha latter one also uses the geocoder package, but only make use of exactly one geocoding service: Google Maps.
In contrast, this very package just integrates the geocoder package without any restrictions in its configuration.
Although I included the [Nominatim/OpenStreetMap data provider](https://github.com/geocoder-php/nominatim-provider) as default here, you can use something [completely different](https://github.com/geocoder-php/Geocoder#providers),
or with just a different config. This is what this makes this package a little more flexible.

In the [`Configuration/Objects.yaml`](./Configuration/Objects.yaml) file, the [object management](https://flowframework.readthedocs.io/en/stable/TheDefinitiveGuide/PartIII/ObjectManagement.html)
feature of NEOS is used to configure the geocoder/data provider used to get coordinates from your address data.

```yaml
Geocoder\StatefulGeocoder:
  className: 'Geocoder\StatefulGeocoder'
Geocoder\Provider\Nominatim\Nominatim:
  className: 'Geocoder\Provider\Nominatim\Nominatim'

Ujamii\Geocoder\Service\GeocodingService:
  properties:
    geocoder:
      object:
        name: 'Geocoder\StatefulGeocoder'
        arguments:
          1:
            object:
              name: 'Geocoder\Provider\Nominatim\Nominatim'
              arguments:
                1:
                  object: 'Http\Adapter\Guzzle6\Client'
                2:
                  value: 'https://nominatim.openstreetmap.org'
                3:
                  value: 'NEOS CMS Ujamii.Geocoder'
          2:
            value: 'de'

```

As you can see, all the constructor arguments for the used objects are provided and can, of course, be changed
by your own package and config.

In the [`Configuration/Settings.yaml`](./Configuration/Settings.yaml) file, the property config can also be changed. So let's
say you need some more properties, like a country or state. You can just add those to your NodeType or create your own mixin,
which may have `Ujamii.Geocoder:Mixin.AddressData` as supertype again. The properties will be joined by a _space_ character when
sent to the data provider.

But you are even free to exchange this part as well. The event after saving your node will check for a configured type, 
so if you created your own one, just set it in your settings:

```yaml
Ujamii:
  Geocoder:
    GeocodingService:
      observedNodeType: 'Ujamii.Geocoder:Mixin.AddressData'
      observedProperties: ['street', 'zip', 'city']
      mandatoryProperties: ['street', 'zip', 'city']
```

Now, let's assume everything is configured the way you like, and you want to display a [nice map](https://github.com/webexcess/openstreetmap) with those documents
added as map markers along with nice tooltips and an info popup (we also added some more properties):

(for this example, install WebExcess.OpenStreetMap, but any other map will work as well)
```neosfusion
prototype(Your.Package:Content.BranchLocationMap) < prototype(Neos.Neos:ContentComponent) {
    @context.branches = Neos.Fusion:Loop {
        items = ${q(site).find('[instanceof Your.Package:Document.BranchLocation]').get()}
        @glue = ','
        itemRenderer = Neos.Fusion:RawArray {
            type = "Feature"
            properties {
                tooltip = ${q(item).property('title')}
                popup = Your.Package:Component.Molecule.BranchLocationMapPopup {
                    company = ${q(item).property('company')}
                    street = ${q(item).property('street')}
                    zip = ${q(item).property('zip')}
                    city = ${q(item).property('city')}
                    phone = ${q(item).property('phone')}
                    email = ${q(item).property('email')}
                }
            }
            geometry {
                type = "Point"
                coordinates = ${[q(item).property('longitude'), q(item).property('latitude')]}
            }
            @process.json = ${Json.stringify(value)}
        }
    }

    map = WebExcess.OpenStreetMap:Map.Component {
        json = ${'[' + branches + ']'}
    }

    renderer = afx`
        <div>
            {props.map}
        </div>
    `
}

prototype(Your.Package:Component.Molecule.BranchLocationMapPopup) < prototype(Neos.Fusion:Component) {
    company = ''
    street = ''
    zip = ''
    city = ''
    phone = ''
    email = ''

    renderer = afx`
        <p>
            {String.nl2br(props.company)}<br/>
            {props.street}<br/>
            {props.zip} {props.city}
        </p>
        <p>
            {props.phone}
            {props.email}
        </p>
    `
}
```

### Using a different provider

Say, you want to use Google Maps as data provider. First, install the package:

```shell
composer require geocoder-php/google-maps-provider
```

Next, adjust the config:

```yaml
Geocoder\StatefulGeocoder:
  className: 'Geocoder\StatefulGeocoder'
Geocoder\Provider\GoogleMaps\GoogleMaps:
  className: 'Geocoder\Provider\GoogleMaps\GoogleMaps'

Ujamii\Geocoder\Service\GeocodingService:
  properties:
    geocoder:
      object:
        name: 'Geocoder\StatefulGeocoder'
        arguments:
          1:
            object:
              name: 'Geocoder\Provider\GoogleMaps\GoogleMaps'
              arguments:
                1:
                  object: 'Http\Adapter\Guzzle6\Client'
                2:
                  value: null
                3:
                  value: '<your-api-key>'
          2:
            value: 'de'
```

The same steps if you, for instance, dont want to use the default Guzzle 6 adapter. Just add your [desired library](https://packagist.org/providers/php-http/client-implementation):

```shell
composer require ...
```

and then configure it in the `Objects.yaml` providing optional config as constructor parameters, if you want.

## TODOs

- feature: multi language
- feature: Command Controller for filling empty values (like for imported nodes)

## License and Contribution

[GPLv3](LICENSE)

As this is OpenSource, you are very welcome to contribute by reporting bugs, improve the code, write tests or
whatever you are able to do to improve the project.

If you want to do me a favour, buy me something from my [Amazon wishlist](https://www.amazon.de/registry/wishlist/2C7LSRMLEAD4F).
