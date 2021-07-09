# ANSIBLE TOOLS FOR SELLYOURSAAS #

Tools to manage remotely several servers. 



Example:

cd ~/git/sellyoursaas/scripts/ansible

ansible-playbook -i hosts.example --limit myserver1 update_sellyoursaas_conf.yml
ansible-playbook -i hosts.example --limit myserver1 launch_git_update_sellyoursaas.yml
ansible-playbook -i hosts.example --limit myserver1 launch_clean.yml


