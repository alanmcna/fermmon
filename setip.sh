sudo nmcli c m "preconfigured" ipv4.addresses 192.168.0.16/24 ipv4.method manual
sudo nmcli c m "preconfigured" ipv4.gateway 192.168.0.1
sudo nmcli c m "preconfigured" ipv4.dns "192.168.0.4"
sudo nmcli c down "preconfigured" && sudo nmcli c up "preconfigured"
